<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\Staff;
use Carbon\CarbonImmutable;

// 캘린더 화면용 데이터: 기간 안의 예약·그룹 일정을 FullCalendar 이벤트 모양으로, 영업시간을 캘린더 설정 모양으로
class CalendarService
{
    public function events(Shop $shop, Staff $staff, CarbonImmutable $from, CarbonImmutable $to, array $resourceIds, bool $withCanceled): array
    {
        // 직원은 자기 resource 만 (대표는 전부)
        $ids = array_values(array_intersect($resourceIds, $staff->visibleResourceIds()));

        return [
            ...$this->reservationEvents($shop, $ids, $from, $to, $withCanceled),
            ...$this->sessionEvents($shop, $ids, $from, $to, $withCanceled),
        ];
    }

    // 1:1·공간 예약 (그룹 신청은 일정 블록 하나로 묶어서 보이므로 뺀다)
    private function reservationEvents(Shop $shop, array $ids, CarbonImmutable $from, CarbonImmutable $to, bool $withCanceled): array
    {
        return $shop->reservations()
            ->individual()
            ->whereIn('resource_id', $ids)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->when(! $withCanceled, fn ($q) => $q->occupying())
            ->with(['customer', 'resource', 'items'])
            ->orderBy('start_at')
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'title' => $r->customer->name,
                'start' => $r->start_at->format('Y-m-d\TH:i:s'),
                'end' => $r->end_at->format('Y-m-d\TH:i:s'),
                'classNames' => ['ev', 'ev-'.$r->status],   // 색은 담당 색(color)으로 화면이 연하게 칠한다
                'editable' => $r->status !== 'canceled',
                'extendedProps' => [
                    'kind' => 'reservation',
                    'status' => $r->status,
                    'statusLabel' => Reservation::STATUSES[$r->status],
                    'resource' => $r->resource->name,
                    'resourceId' => $r->resource_id,
                    'color' => $r->resource->color,
                    'services' => $r->items->pluck('service_name')->implode(', '),
                ],
            ])
            ->all();
    }

    // 그룹 일정: 블록 하나에 "신청 인원/정원"
    private function sessionEvents(Shop $shop, array $ids, CarbonImmutable $from, CarbonImmutable $to, bool $withCanceled): array
    {
        return $shop->classSessions()
            ->whereIn('resource_id', $ids)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->when(! $withCanceled, fn ($q) => $q->active())
            ->with(['service', 'resource'])
            ->withSum(['reservations as taken' => fn ($q) => $q->occupying()], 'party_size')
            ->orderBy('start_at')
            ->get()
            ->map(fn (ClassSession $s) => [
                'id' => 's'.$s->id,
                'title' => $s->service->name,
                'start' => $s->start_at->format('Y-m-d\TH:i:s'),
                'end' => $s->end_at->format('Y-m-d\TH:i:s'),
                'classNames' => ['ev', 'ev-session', $s->isCanceled() ? 'ev-canceled' : ''],
                'editable' => ! $s->isCanceled(),
                'extendedProps' => [
                    'kind' => 'session',
                    'sessionId' => $s->id,
                    'status' => $s->isCanceled() ? 'canceled' : 'confirmed',
                    'statusLabel' => '취소',
                    'resource' => $s->resource->name,
                    'resourceId' => $s->resource_id,
                    'color' => $s->resource->color,
                    'taken' => (int) $s->taken,
                    'capacity' => $s->capacity,
                ],
            ])
            ->all();
    }

    // 매장 영업시간 → FullCalendar businessHours (회색 아닌 칸)
    public function businessHours(Shop $shop): array
    {
        $days = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
        $result = [];

        foreach ($shop->business_hours ?? [] as $key => $h) {
            if ($h) {
                $result[] = ['daysOfWeek' => [$days[$key]], 'startTime' => $h['open'], 'endTime' => $h['close']];
            }
        }

        return $result;
    }

    // 캘린더에 보일 시간 범위: 가장 이른 오픈 1시간 전 ~ 가장 늦은 마감 1시간 후
    public function visibleRange(Shop $shop): array
    {
        $hours = array_filter($shop->business_hours ?? []);
        $open = $hours ? min(array_column($hours, 'open')) : '09:00';
        $close = $hours ? max(array_column($hours, 'close')) : '21:00';

        $min = max(0, (int) substr($open, 0, 2) - 1);
        $max = min(24, (int) substr($close, 0, 2) + (substr($close, 3, 2) === '00' ? 1 : 2));

        return [sprintf('%02d:00:00', $min), sprintf('%02d:00:00', $max)];
    }
}
