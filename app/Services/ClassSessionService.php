<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// 그룹 일정(정원제) 만들기·수정·이동·취소. 신청(자리 잡기)은 ReservationService::bookSession
class ClassSessionService
{
    private const MAX_SESSIONS = 100;   // 반복으로 한 번에 만들 수 있는 최대 개수

    private const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];

    public function __construct(
        private AvailabilityService $availability,
        private ReservationService $reservations,
        private AuditLogger $audit,
    ) {}

    /**
     * 일정 만들기. repeat_days(0=일 ~ 6=토)가 있으면 첫 날짜부터 repeat_weeks 주 동안 그 요일마다 만든다
     * 하나라도 담당자 시간이 겹치면 전부 만들지 않는다 (전부 아니면 전무)
     *
     * @param  array{service_id:int,resource_id:int,date:string,start_time:string,capacity?:?int,memo?:?string,repeat_days?:int[],repeat_weeks?:?int}  $data
     * @return Collection<int, ClassSession>
     */
    public function create(Shop $shop, ?Staff $by, array $data): Collection
    {
        return DB::transaction(function () use ($shop, $by, $data) {
            $resource = $this->availability->lockResource($shop, $data['resource_id'], mustBeActive: true);
            $service = $this->groupService($shop, $data['service_id']);

            if (! $resource->services()->whereKey($service->id)->exists()) {
                throw ValidationException::withMessages(['service_id' => "{$resource->name}이(가) 담당하지 않는 ".term('service', $shop).'입니다.']);
            }

            $sessions = collect();
            foreach ($this->starts($data) as $start) {
                [$end, $occupied] = $this->times($start, $service);
                $this->withDate($start, fn () => $this->availability->assertFree($resource, $start, $occupied));

                $session = $shop->classSessions()->create([
                    'service_id' => $service->id,
                    'resource_id' => $resource->id,
                    'start_at' => $start,
                    'end_at' => $end,
                    'occupied_until' => $occupied,
                    'capacity' => $data['capacity'] ?? $service->capacity,
                    'memo' => $data['memo'] ?? null,
                    'created_by' => $by?->id,
                ]);
                $this->audit->log($session, 'created', $by);
                $sessions->push($session);
            }

            return $sessions;
        });
    }

    // 정원·메모 수정. 이미 신청한 인원보다 정원을 줄일 수는 없다
    public function update(ClassSession $session, ?Staff $by, array $data): ClassSession
    {
        return DB::transaction(function () use ($session, $by, $data) {
            $session = $this->lock($session);
            $this->assertNotCanceled($session);

            $taken = $this->reservations->seatsTaken($session);
            if ($data['capacity'] < $taken) {
                throw ValidationException::withMessages(['capacity' => "이미 {$taken}명이 신청해서 정원을 {$taken}명보다 줄일 수 없습니다."]);
            }

            $before = $session->only('capacity', 'memo');
            $session->update(['capacity' => $data['capacity'], 'memo' => $data['memo'] ?? null]);

            $changes = $this->audit->diff($before, $session->only('capacity', 'memo'));
            if ($changes) {
                $this->audit->log($session, 'updated', $by, $changes);
            }

            return $session;
        });
    }

    // 드래그로 시간 옮기기: 일정과 신청들을 함께 옮긴다
    public function move(ClassSession $session, ?Staff $by, CarbonImmutable $newStart): ClassSession
    {
        return DB::transaction(function () use ($session, $by, $newStart) {
            // 잠금 순서: 담당자 → 일정 (1:1 예약·일정 만들기와 같은 순서)
            $resource = $this->availability->lockResource($session->shop, $session->resource_id);
            $session = $this->lock($session);
            $this->assertNotCanceled($session);

            $duration = (int) $session->start_at->diffInMinutes($session->end_at);
            $occupiedMin = (int) $session->start_at->diffInMinutes($session->occupied_until);
            $times = [
                'start_at' => $newStart,
                'end_at' => $newStart->addMinutes($duration),
                'occupied_until' => $newStart->addMinutes($occupiedMin),
            ];

            $this->availability->assertFree($resource, $newStart, $times['occupied_until'], ignoreSessionId: $session->id, field: 'start');

            $old = $session->start_at->format('Y-m-d H:i');
            $session->update($times);
            $session->reservations()->update($times);

            $this->audit->log($session, 'moved', $by, ['start_at' => [$old, $newStart->format('Y-m-d H:i')]]);

            return $session;
        });
    }

    // 일정 취소: 대기·확정 신청을 모두 취소로 (완료·노쇼는 그대로 둔다)
    public function cancel(ClassSession $session, ?Staff $by, ?string $reason = null): ClassSession
    {
        return DB::transaction(function () use ($session, $by, $reason) {
            $session = $this->lock($session);
            if ($session->isCanceled()) {
                return $session;
            }

            $session->update(['canceled_at' => now(), 'cancel_reason' => $reason]);

            $active = $session->reservations()->whereIn('status', ['pending', 'confirmed'])->lockForUpdate()->get();
            foreach ($active as $reservation) {
                $old = $reservation->status;
                $this->reservations->applyStatus($reservation, 'canceled', $reason ?: '일정 취소');
                $this->audit->log($reservation, 'status_changed', $by, ['status' => [$old, 'canceled']]);
            }

            $this->audit->log($session, 'canceled', $by, $reason ? ['cancel_reason' => [null, $reason]] : []);

            return $session;
        });
    }

    // ── 도우미 ─────────────────────────────────────────────

    private function groupService(Shop $shop, int|string $id): Service
    {
        $service = $shop->services()->whereKey($id)->where('booking_type', 'group')->first();

        if (! $service) {
            throw ValidationException::withMessages(['service_id' => '그룹 (정원제) '.term('service', $shop).'을(를) 선택하세요.']);
        }

        return $service;
    }

    /** @return CarbonImmutable[] 만들 일정들의 시작 시각 */
    private function starts(array $data): array
    {
        $first = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['start_time']}", config('app.timezone'))->startOfMinute();
        $days = array_map('intval', $data['repeat_days'] ?? []);
        if (! $days) {
            return [$first];
        }

        $starts = [];
        for ($i = 0; $i < 7 * (int) ($data['repeat_weeks'] ?? 1); $i++) {
            $day = $first->addDays($i);
            if (in_array($day->dayOfWeek, $days, true)) {
                $starts[] = $day;
            }
        }

        if (! $starts) {
            throw ValidationException::withMessages(['repeat_days' => '반복할 요일을 확인하세요.']);
        }
        if (count($starts) > self::MAX_SESSIONS) {
            throw ValidationException::withMessages(['repeat_weeks' => '한 번에 '.self::MAX_SESSIONS.'개까지 만들 수 있습니다.']);
        }

        return $starts;
    }

    // 시작 → [끝, 자리 비는 시각]
    private function times(CarbonImmutable $start, Service $service): array
    {
        $end = $start->addMinutes($service->duration_min);

        return [$end, $end->addMinutes($service->buffer_min)];
    }

    // 반복 생성 중 겹치면 몇 월 며칠 것인지 알 수 있게 날짜를 앞에 붙인다
    private function withDate(CarbonImmutable $start, callable $check): void
    {
        try {
            $check();
        } catch (ValidationException $e) {
            $label = $start->format('n/j').'('.self::WEEKDAYS[$start->dayOfWeek].') ';
            throw ValidationException::withMessages(['start_time' => $label.collect($e->errors())->flatten()->first()]);
        }
    }

    private function lock(ClassSession $session): ClassSession
    {
        return ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
    }

    private function assertNotCanceled(ClassSession $session): void
    {
        if ($session->isCanceled()) {
            throw ValidationException::withMessages(['status' => '취소된 일정은 바꿀 수 없습니다.']);
        }
    }
}
