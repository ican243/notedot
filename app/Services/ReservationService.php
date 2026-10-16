<?php

namespace App\Services;

use App\Events\ReservationChanged;
use App\Models\ClassSession;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// 예약 만들기·수정·이동·상태 변경 + 그룹 일정 신청. 겹침·정원 검사는 반드시 여기서 (컨트롤러에서 하지 않음)
class ReservationService
{
    public function __construct(
        private CustomerService $customers,
        private AuditLogger $audit,
        private AvailabilityService $availability,
    ) {}

    /**
     * 새 예약 (1:1·공간)
     *
     * @param  array{customer_id?:?int,customer_name?:?string,customer_phone?:?string,resource_id:int,service_ids:int[],date:string,start_time:string,party_size?:int,channel?:string,status?:string,customer_request?:?string,staff_memo?:?string}  $data
     */
    public function create(Shop $shop, ?Staff $by, array $data): Reservation
    {
        return DB::transaction(function () use ($shop, $by, $data) {
            // 잠금은 트랜잭션의 맨 처음에 (이유는 AvailabilityService::assertFree 주석 참고)
            $resource = $this->availability->lockResource($shop, $data['resource_id'], mustBeActive: true);
            $this->assertPartySize($resource, $data['party_size'] ?? 1);
            $customer = $this->customers->resolve($shop, $data['customer_id'] ?? null, $data['customer_name'] ?? null, $data['customer_phone'] ?? null);
            $services = $this->servicesFor($shop, $resource, $data['service_ids']);
            [$start, $end, $occupied] = $this->times($this->startAt($data), $services);

            $this->availability->assertFree($resource, $start, $occupied);

            $reservation = $shop->reservations()->create([
                'customer_id' => $customer->id,
                'resource_id' => $resource->id,
                'start_at' => $start,
                'end_at' => $end,
                'occupied_until' => $occupied,
                'party_size' => $data['party_size'] ?? 1,
                'status' => $data['status'] ?? 'confirmed',
                'channel' => $data['channel'] ?? 'phone',
                'total_price' => $services->sum('price'),
                'deposit' => $services->sum('deposit'),
                'customer_request' => $data['customer_request'] ?? null,
                'staff_memo' => $data['staff_memo'] ?? null,
                'created_by' => $by?->id,
            ]);
            $reservation->items()->createMany($this->itemRows($services));

            $this->audit->log($reservation, 'created', $by);
            ReservationChanged::dispatch($reservation, 'created', $by);

            return $reservation;
        });
    }

    /**
     * 그룹 일정에 자리 신청. 일정 행을 잠그고 (신청 인원 합 + 내 인원 ≤ 정원) 확인
     *
     * @param  array{customer_id?:?int,customer_name?:?string,customer_phone?:?string,party_size?:int,channel?:string,status?:string,customer_request?:?string,staff_memo?:?string}  $data
     */
    public function bookSession(ClassSession $session, ?Staff $by, array $data): Reservation
    {
        return DB::transaction(function () use ($session, $by, $data) {
            $session = $this->lockSession($session);
            if ($session->isCanceled()) {
                throw ValidationException::withMessages(['status' => '취소된 일정에는 신청할 수 없습니다.']);
            }

            $shop = $session->shop;
            $partySize = $data['party_size'] ?? 1;
            $customer = $this->customers->resolve($shop, $data['customer_id'] ?? null, $data['customer_name'] ?? null, $data['customer_phone'] ?? null);

            $already = $session->reservations()->occupying()->where('customer_id', $customer->id)->lockForUpdate()->exists();
            if ($already) {
                throw ValidationException::withMessages(['customer_id' => "{$customer->name}님은 이미 이 일정에 신청했습니다."]);
            }
            $this->assertSeats($session, $partySize);

            $service = $session->service;
            $reservation = $shop->reservations()->create([
                'customer_id' => $customer->id,
                'resource_id' => $session->resource_id,
                'class_session_id' => $session->id,
                'start_at' => $session->start_at,
                'end_at' => $session->end_at,
                'occupied_until' => $session->occupied_until,
                'party_size' => $partySize,
                'status' => $data['status'] ?? 'confirmed',
                'channel' => $data['channel'] ?? 'phone',
                'total_price' => $service->price * $partySize,      // 그룹은 1인 가격 × 인원
                'deposit' => $service->deposit * $partySize,
                'customer_request' => $data['customer_request'] ?? null,
                'staff_memo' => $data['staff_memo'] ?? null,
                'created_by' => $by?->id,
            ]);
            $reservation->items()->createMany($this->itemRows(collect([$service])));

            $this->audit->log($reservation, 'created', $by);
            ReservationChanged::dispatch($reservation, 'created', $by);

            return $reservation;
        });
    }

    // 예약 내용 수정 (고객, 담당, 상품, 시간, 메모). 상태는 changeStatus 로 따로
    public function update(Reservation $reservation, ?Staff $by, array $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $by, $data) {
            $reservation = $this->lockReservation($reservation);
            $this->assertEditable($reservation);
            $shop = $reservation->shop;
            $before = $this->snapshot($reservation);

            $customer = $this->customers->resolve($shop, $data['customer_id'] ?? null, $data['customer_name'] ?? null, $data['customer_phone'] ?? null);
            $resourceChanged = (int) $data['resource_id'] !== $reservation->resource_id;
            $resource = $this->availability->lockResource($shop, $data['resource_id'], mustBeActive: $resourceChanged);
            $this->assertPartySize($resource, $data['party_size'] ?? 1);
            $services = $this->servicesFor($shop, $resource, $data['service_ids']);
            [$start, $end, $occupied] = $this->times($this->startAt($data), $services);

            $this->availability->assertFree($resource, $start, $occupied, ignoreReservationId: $reservation->id);

            $reservation->update([
                'customer_id' => $customer->id,
                'resource_id' => $resource->id,
                'start_at' => $start,
                'end_at' => $end,
                'occupied_until' => $occupied,
                'party_size' => $data['party_size'] ?? 1,
                'channel' => $data['channel'] ?? $reservation->channel,
                'total_price' => $services->sum('price'),
                'deposit' => $services->sum('deposit'),
                'customer_request' => $data['customer_request'] ?? null,
                'staff_memo' => $data['staff_memo'] ?? null,
            ]);
            $reservation->items()->delete();
            $reservation->items()->createMany($this->itemRows($services));

            $changes = $this->audit->diff($before, $this->snapshot($reservation->refresh()));
            if ($changes) {
                $this->audit->log($reservation, 'updated', $by, $changes);
                ReservationChanged::dispatch($reservation, 'updated', $by);
            }

            return $reservation;
        });
    }

    // 캘린더에서 드래그로 시간만 옮기기 (걸리는 시간·정리 시간은 그대로)
    public function move(Reservation $reservation, ?Staff $by, CarbonImmutable $newStart): Reservation
    {
        return DB::transaction(function () use ($reservation, $by, $newStart) {
            $reservation = $this->lockReservation($reservation);
            $this->assertEditable($reservation);
            $resource = $this->availability->lockResource($reservation->shop, $reservation->resource_id);

            $duration = (int) $reservation->start_at->diffInMinutes($reservation->end_at);
            $occupiedMin = (int) $reservation->start_at->diffInMinutes($reservation->occupied_until);
            $end = $newStart->addMinutes($duration);
            $occupied = $newStart->addMinutes($occupiedMin);

            $this->availability->assertFree($resource, $newStart, $occupied, ignoreReservationId: $reservation->id, field: 'start');

            $old = $reservation->start_at->format('Y-m-d H:i');
            $reservation->update(['start_at' => $newStart, 'end_at' => $end, 'occupied_until' => $occupied]);

            $this->audit->log($reservation, 'moved', $by, ['start_at' => [$old, $newStart->format('Y-m-d H:i')]]);
            ReservationChanged::dispatch($reservation, 'moved', $by);

            return $reservation;
        });
    }

    // 상태 바꾸기: 확정 / 완료 / 노쇼 / 취소 / 대기
    public function changeStatus(Reservation $reservation, ?Staff $by, string $status, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $by, $status, $reason) {
            // 그룹 신청은 일정 → 예약 순서로 잠근다 (일정 취소·신청과 같은 순서 → 서로 기다리다 멈추는 일 방지)
            $session = $reservation->class_session_id ? $this->lockSession($reservation->classSession) : null;
            $reservation = $this->lockReservation($reservation);
            $old = $reservation->status;
            if ($old === $status) {
                return $reservation;
            }

            // 취소된 예약을 되살리면 그 사이 다른 예약이 자리를 잡았을 수 있으므로 다시 검사
            if ($old === 'canceled') {
                if ($session) {
                    if ($session->isCanceled()) {
                        throw ValidationException::withMessages(['status' => '취소된 일정의 신청은 되돌릴 수 없습니다.']);
                    }
                    $this->assertSeats($session, $reservation->party_size, ignoreId: $reservation->id, field: 'status');
                } else {
                    $resource = $this->availability->lockResource($reservation->shop, $reservation->resource_id);
                    $this->availability->assertFree($resource, $reservation->start_at->toImmutable(), $reservation->occupied_until->toImmutable(), ignoreReservationId: $reservation->id, field: 'status');
                }
            }

            $this->applyStatus($reservation, $status, $reason);

            $changes = ['status' => [$old, $status]];
            if ($reason) {
                $changes['cancel_reason'] = [null, $reason];
            }
            $this->audit->log($reservation, 'status_changed', $by, $changes);
            ReservationChanged::dispatch($reservation, 'status', $by);

            return $reservation;
        });
    }

    // 상태 저장 + 노쇼 횟수 맞추기 (일정 취소에서도 같이 쓴다. 잠금·검사는 부르는 쪽 책임)
    public function applyStatus(Reservation $reservation, string $status, ?string $reason = null): void
    {
        $old = $reservation->status;
        $reservation->fill([
            'status' => $status,
            'canceled_at' => $status === 'canceled' ? now() : null,
            'cancel_reason' => $status === 'canceled' ? $reason : null,
        ])->save();

        // 노쇼 횟수: 노쇼가 되면 +1, 노쇼에서 다른 상태로 바뀌면 -1
        if ($status === 'no_show') {
            $reservation->customer()->increment('no_show_count');
        } elseif ($old === 'no_show') {
            $reservation->customer()->where('no_show_count', '>', 0)->decrement('no_show_count');
        }
    }

    // 일정에서 차지된 자리 수 = 취소 아닌 신청의 인원 합
    public function seatsTaken(ClassSession $session, ?int $ignoreId = null): int
    {
        return (int) $session->reservations()
            ->occupying()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->lockForUpdate()   // 잠금 읽기 = 최신 상태 (AvailabilityService::assertFree 주석과 같은 이유)
            ->sum('party_size');
    }

    // ── 검사 ─────────────────────────────────────────────

    private function assertSeats(ClassSession $session, int $partySize, ?int $ignoreId = null, string $field = 'party_size'): void
    {
        $taken = $this->seatsTaken($session, $ignoreId);

        if ($taken + $partySize > $session->capacity) {
            $left = max(0, $session->capacity - $taken);
            throw ValidationException::withMessages([
                $field => $left ? "남은 자리가 {$left}명뿐입니다. (정원 {$session->capacity}명)" : "정원 {$session->capacity}명이 모두 찼습니다.",
            ]);
        }
    }

    // 인원이 resource 의 최대 인원(공간 크기)보다 많으면 거절. 최대 인원이 비어 있으면 제한 없음
    private function assertPartySize(Resource $resource, int $partySize): void
    {
        if ($resource->max_party && $partySize > $resource->max_party) {
            throw ValidationException::withMessages([
                'party_size' => "{$resource->name}은(는) 최대 {$resource->max_party}명까지 예약할 수 있습니다.",
            ]);
        }
    }

    private function assertEditable(Reservation $reservation): void
    {
        if ($reservation->class_session_id) {
            throw ValidationException::withMessages(['status' => '그룹 일정 신청은 일정 창에서 관리합니다.']);
        }
        if ($reservation->status === 'canceled') {
            throw ValidationException::withMessages(['status' => '취소된 예약은 먼저 상태를 되돌린 뒤 수정하세요.']);
        }
    }

    // 고른 상품들: 같은 매장 것인지, 그룹 상품이 아닌지, resource 종류에 맞는지, 이 resource 가 할 수 있는지 확인
    private function servicesFor(Shop $shop, Resource $resource, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $services = $shop->services()->whereIn('id', $ids)->get()
            ->sortBy(fn (Service $s) => array_search($s->id, $ids))->values();

        if ($services->count() !== count($ids) || $services->isEmpty()) {
            throw ValidationException::withMessages(['service_ids' => term('service', $shop).'을(를) 다시 선택하세요.']);
        }
        if ($services->contains('booking_type', 'group')) {
            throw ValidationException::withMessages(['service_ids' => '그룹 (정원제) 상품은 일정에 신청합니다.']);
        }

        // 공간에는 공간 대여 상품만, 사람에게는 1:1 상품만
        $expected = $resource->type === 'space' ? 'space' : 'one_to_one';
        if ($services->contains(fn (Service $s) => $s->booking_type !== $expected)) {
            throw ValidationException::withMessages([
                'service_ids' => "{$resource->name}에는 예약할 수 없는 ".term('service', $shop).'이(가) 있습니다.',
            ]);
        }

        $allowed = $resource->services()->whereIn('services.id', $ids)->count();
        if ($allowed !== count($ids)) {
            throw ValidationException::withMessages([
                'service_ids' => "{$resource->name}이(가) 담당하지 않는 ".term('service', $shop).'이(가) 있습니다.',
            ]);
        }

        return $services;
    }

    // ── 도우미 ─────────────────────────────────────────────

    private function lockReservation(Reservation $reservation): Reservation
    {
        return Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
    }

    private function lockSession(ClassSession $session): ClassSession
    {
        return ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
    }

    private function startAt(array $data): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['start_time']}", config('app.timezone'))->startOfMinute();
    }

    // 시작 → [시작, 끝(소요 시간 합), 자리 비는 시각(끝 + 가장 긴 정리 시간)]
    private function times(CarbonImmutable $start, Collection $services): array
    {
        $end = $start->addMinutes($services->sum('duration_min'));

        return [$start, $end, $end->addMinutes($services->max('buffer_min'))];
    }

    private function itemRows(Collection $services): array
    {
        return $services->map(fn (Service $s) => [
            'service_id' => $s->id,
            'service_name' => $s->name,
            'duration_min' => $s->duration_min,
            'price' => $s->price,
        ])->all();
    }

    // 변경 이력에 남길 "사람이 읽을 수 있는" 모양
    private function snapshot(Reservation $r): array
    {
        $r->loadMissing('customer', 'resource', 'items');

        return [
            'customer' => $r->customer->name,
            'resource' => $r->resource->name,
            'start_at' => $r->start_at->format('Y-m-d H:i'),
            'services' => $r->items->pluck('service_name')->implode(', '),
            'party_size' => $r->party_size,
            'channel' => Reservation::CHANNELS[$r->channel],
            'customer_request' => $r->customer_request,
            'staff_memo' => $r->staff_memo,
        ];
    }
}
