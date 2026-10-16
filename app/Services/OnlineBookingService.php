<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// 고객이 예약 화면에서 직접 하는 예약. 화면이 보낸 시각을 믿지 않고 SlotService 로 다시 확인한 뒤 ReservationService 로 저장한다
class OnlineBookingService
{
    public function __construct(
        private SlotService $slots,
        private ReservationService $reservations,
        private PaymentService $payments,
    ) {}

    /**
     * @param  array{service_id:int,resource_id?:?int,date?:string,start_time?:string,class_session_id?:?int,customer_name:string,customer_phone:string,party_size?:?int,customer_request?:?string,marketing?:bool}  $data
     */
    public function book(Shop $shop, array $data): Reservation
    {
        $service = $this->service($shop, $data['service_id']);
        $common = [
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'party_size' => $data['party_size'] ?? 1,
            'channel' => 'online',
            // 보증금이 있는 상품은 결제 전까지 "대기" → 결제가 확인되면 확정
            'status' => $service->deposit > 0 ? 'pending' : 'confirmed',
            'customer_request' => $data['customer_request'] ?? null,
        ];

        return DB::transaction(function () use ($shop, $service, $data, $common) {
            $reservation = $service->booking_type === 'group'
                ? $this->bookSession($shop, $service, $data, $common)
                : $this->bookTime($shop, $service, $data, $common);

            $this->saveConsents($reservation, (bool) ($data['marketing'] ?? false));

            // 보증금이 있으면 결제 대기를 만든다. hold_minutes 안에 결제하지 않으면 예약이 자동 취소된다
            if ($reservation->deposit > 0) {
                $this->payments->start($reservation);
            }

            return $reservation;
        });
    }

    // 고객 화면에 보여 줄 resource: 예약을 받는 중이고 이 상품을 담당
    public function resourcesFor(Shop $shop, Service $service): Collection
    {
        return $shop->resources()
            ->where('is_active', true)
            ->whereHas('services', fn ($q) => $q->whereKey($service->id))
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function service(Shop $shop, int|string $id): Service
    {
        $service = $shop->services()->where('is_active', true)->find($id);
        if (! $service) {
            throw ValidationException::withMessages(['service_id' => term('service', $shop).'을(를) 다시 선택하세요.']);
        }

        return $service;
    }

    // 1:1·공간: 고른 resource (없으면 "상관없음" → 그 시각에 빈 resource 중 그날 예약이 적은 순)
    private function bookTime(Shop $shop, Service $service, array $data, array $common): Reservation
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['start_time']}", config('app.timezone'))->startOfMinute();
        $resources = $this->resourcesFor($shop, $service);

        if (! empty($data['resource_id'])) {
            $resources = $resources->where('id', (int) $data['resource_id'])->values();
            if ($resources->isEmpty()) {
                throw ValidationException::withMessages(['resource_id' => term('resource', $shop).'을(를) 다시 선택하세요.']);
            }
        } else {
            $resources = $this->leastBusyFirst($resources, $start);
        }

        foreach ($resources as $resource) {
            if (! $this->slots->isBookable($shop, $resource, $service, $start)) {
                continue;
            }
            try {
                return $this->reservations->create($shop, null, $common + [
                    'resource_id' => $resource->id,
                    'service_ids' => [$service->id],
                    'date' => $data['date'],
                    'start_time' => $data['start_time'],
                ]);
            } catch (ValidationException $e) {
                // 인원 초과처럼 이 resource 로는 안 되는 이유면 그대로 알려 준다. 겹침이면 다음 resource 로
                if (! array_key_exists('start_time', $e->errors()) || ! empty($data['resource_id'])) {
                    throw $e;
                }
            }
        }

        // 화면에서 고른 시간이 그 사이 찼거나, 원래 받을 수 없는 시간
        throw ValidationException::withMessages(['start_time' => '예약할 수 없는 시간입니다. 다른 시간을 골라 주세요.']);
    }

    private function bookSession(Shop $shop, Service $service, array $data, array $common): Reservation
    {
        $session = $shop->classSessions()->active()->where('service_id', $service->id)->find($data['class_session_id'] ?? 0);

        if (! $session || $session->start_at < $this->slots->earliest() || ! $session->resource->is_active) {
            throw ValidationException::withMessages(['class_session_id' => '신청할 수 없는 일정입니다. 다시 선택해 주세요.']);
        }

        return $this->reservations->bookSession($session, null, $common);
    }

    // 그날 예약이 적은 resource 부터 (한 사람에게 몰리지 않게)
    private function leastBusyFirst(Collection $resources, CarbonImmutable $start): Collection
    {
        $counts = Reservation::whereIn('resource_id', $resources->pluck('id'))
            ->occupying()
            ->whereDate('start_at', $start->toDateString())
            ->selectRaw('resource_id, count(*) as n')->groupBy('resource_id')
            ->pluck('n', 'resource_id');

        return $resources->sortBy(fn (Resource $r) => [$counts[$r->id] ?? 0, $r->sort_order, $r->id])->values();
    }

    // 개인정보 동의(필수)는 처음 동의한 때, 마케팅 수신 동의(선택)는 동의했을 때만 기록. 따로 저장한다
    private function saveConsents(Reservation $reservation, bool $marketing): void
    {
        $customer = $reservation->customer;
        $customer->privacy_agreed_at ??= now();
        if ($marketing) {
            $customer->marketing_agreed_at ??= now();
        }
        $customer->save();
    }
}
