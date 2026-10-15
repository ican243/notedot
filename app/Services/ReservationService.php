<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// 예약 만들기·수정·이동·상태 변경. 겹침 검사는 반드시 여기서 (컨트롤러에서 하지 않음)
class ReservationService
{
    public function __construct(
        private CustomerService $customers,
        private AuditLogger $audit,
    ) {}

    /**
     * 새 예약
     *
     * @param  array{customer_id?:?int,customer_name?:?string,customer_phone?:?string,resource_id:int,service_ids:int[],date:string,start_time:string,party_size?:int,channel?:string,status?:string,customer_request?:?string,staff_memo?:?string}  $data
     */
    public function create(Shop $shop, ?Staff $by, array $data): Reservation
    {
        return DB::transaction(function () use ($shop, $by, $data) {
            // 잠금은 트랜잭션의 맨 처음에 (아래 "왜 맨 처음?" 설명은 assertAvailable 주석 참고)
            $resource = $this->lockResource($shop, $data['resource_id'], mustBeActive: true);
            $customer = $this->customers->resolve($shop, $data['customer_id'] ?? null, $data['customer_name'] ?? null, $data['customer_phone'] ?? null);
            $services = $this->servicesFor($shop, $resource, $data['service_ids']);
            [$start, $end, $occupied] = $this->times($this->startAt($data), $services);

            $this->assertAvailable($resource, $start, $occupied);

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
            $resource = $this->lockResource($shop, $data['resource_id'], mustBeActive: $resourceChanged);
            $services = $this->servicesFor($shop, $resource, $data['service_ids']);
            [$start, $end, $occupied] = $this->times($this->startAt($data), $services);

            $this->assertAvailable($resource, $start, $occupied, ignoreId: $reservation->id);

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
            $resource = $this->lockResource($reservation->shop, $reservation->resource_id);

            $duration = (int) $reservation->start_at->diffInMinutes($reservation->end_at);
            $occupiedMin = (int) $reservation->start_at->diffInMinutes($reservation->occupied_until);
            $end = $newStart->addMinutes($duration);
            $occupied = $newStart->addMinutes($occupiedMin);

            $this->assertAvailable($resource, $newStart, $occupied, ignoreId: $reservation->id, field: 'start');

            $old = $reservation->start_at->format('Y-m-d H:i');
            $reservation->update(['start_at' => $newStart, 'end_at' => $end, 'occupied_until' => $occupied]);

            $this->audit->log($reservation, 'moved', $by, ['start_at' => [$old, $newStart->format('Y-m-d H:i')]]);

            return $reservation;
        });
    }

    // 상태 바꾸기: 확정 / 완료 / 노쇼 / 취소 / 대기
    public function changeStatus(Reservation $reservation, ?Staff $by, string $status, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $by, $status, $reason) {
            $reservation = $this->lockReservation($reservation);
            $old = $reservation->status;
            if ($old === $status) {
                return $reservation;
            }

            // 취소된 예약을 되살리면 그 사이 다른 예약이 자리를 잡았을 수 있으므로 다시 검사
            if ($old === 'canceled') {
                $resource = $this->lockResource($reservation->shop, $reservation->resource_id);
                $this->assertAvailable($resource, $reservation->start_at->toImmutable(), $reservation->occupied_until->toImmutable(), ignoreId: $reservation->id, field: 'status');
            }

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

            $changes = ['status' => [$old, $status]];
            if ($reason) {
                $changes['cancel_reason'] = [null, $reason];
            }
            $this->audit->log($reservation, 'status_changed', $by, $changes);

            return $reservation;
        });
    }

    // ── 겹침 검사 ──────────────────────────────────────────

    // resource 행을 잠근다 (SELECT ... FOR UPDATE).
    // 같은 resource 에 예약하려는 다른 요청은 이 트랜잭션이 끝날 때까지 여기서 기다린다 → 동시에 같은 시간 예약 불가
    private function lockResource(Shop $shop, int|string $resourceId, bool $mustBeActive = false): Resource
    {
        $resource = Resource::where('shop_id', $shop->id)->whereKey($resourceId)->lockForUpdate()->first();

        if (! $resource) {
            throw ValidationException::withMessages(['resource_id' => term('resource', $shop).'을(를) 찾을 수 없습니다.']);
        }
        if ($mustBeActive && ! $resource->is_active) {
            throw ValidationException::withMessages(['resource_id' => "{$resource->name}은(는) 지금 예약을 받지 않습니다."]);
        }

        return $resource;
    }

    // [start, occupied) 동안 이미 차지된 예약 수가 resource 정원 이상이면 거절
    private function assertAvailable(Resource $resource, CarbonImmutable $start, CarbonImmutable $occupied, ?int $ignoreId = null, string $field = 'start_time'): void
    {
        // lockForUpdate(잠금 읽기)는 "트랜잭션 시작 때 찍어 둔 사진"이 아니라 최신 저장 상태를 읽는다.
        // 일반 SELECT 로 읽으면 MySQL(REPEATABLE READ)은 옛 사진을 보여 줘서,
        // 앞 요청이 방금 저장한 예약을 못 보고 같은 시간에 또 예약이 들어간다 (동시 요청 시험에서 실제로 발생)
        $conflicts = Reservation::where('resource_id', $resource->id)
            ->occupying()
            ->overlapping($start, $occupied)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->with('customer')
            ->orderBy('start_at')
            ->get();

        if ($conflicts->count() >= $resource->capacity) {
            $c = $conflicts->first();
            throw ValidationException::withMessages([
                $field => sprintf(
                    '%s은(는) %s~%s에 이미 예약(%s)이 있습니다.',
                    $resource->name,
                    $c->start_at->format('H:i'),
                    $c->occupied_until->format('H:i'),
                    $c->customer->name,
                ),
            ]);
        }
    }

    // ── 도우미 ─────────────────────────────────────────────

    private function lockReservation(Reservation $reservation): Reservation
    {
        return Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
    }

    private function assertEditable(Reservation $reservation): void
    {
        if ($reservation->status === 'canceled') {
            throw ValidationException::withMessages(['status' => '취소된 예약은 먼저 상태를 되돌린 뒤 수정하세요.']);
        }
    }

    // 고른 상품들: 같은 매장 것인지, 그룹 상품이 아닌지, 이 resource 가 할 수 있는지 확인
    private function servicesFor(Shop $shop, Resource $resource, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $services = $shop->services()->whereIn('id', $ids)->get()
            ->sortBy(fn (Service $s) => array_search($s->id, $ids))->values();

        if ($services->count() !== count($ids) || $services->isEmpty()) {
            throw ValidationException::withMessages(['service_ids' => term('service', $shop).'을(를) 다시 선택하세요.']);
        }
        if ($services->contains('booking_type', 'group')) {
            throw ValidationException::withMessages(['service_ids' => '그룹 (정원제) 상품은 수업 시간표에서 예약합니다.']);
        }

        $allowed = $resource->services()->whereIn('services.id', $ids)->count();
        if ($allowed !== count($ids)) {
            throw ValidationException::withMessages([
                'service_ids' => "{$resource->name}이(가) 담당하지 않는 ".term('service', $shop).'이(가) 있습니다.',
            ]);
        }

        return $services;
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
