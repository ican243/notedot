<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// 고객 예약 화면의 "예약 가능한 시간" 계산
// 가능한 시작 시각 = 근무 시간 안 + 쉬는 시간·휴무 아님 + 기존 예약(정리 시간 포함)·그룹 일정과 안 겹침 + 지금부터 LEAD_MINUTES 뒤
class SlotService
{
    public const BOOKING_DAYS = 30;     // 오늘부터 며칠까지 온라인 예약을 받나

    public const LEAD_MINUTES = 60;     // 지금부터 최소 몇 분 뒤부터 받나

    public function __construct(private ScheduleService $schedules) {}

    // 온라인으로 잡을 수 있는 가장 이른 시작 시각
    public function earliest(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMinutes(self::LEAD_MINUTES);
    }

    public function lastDay(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(self::BOOKING_DAYS - 1);
    }

    /**
     * 날짜별 시작 가능 시각과, 그 시각에 비어 있는 resource 들
     *
     * @param  Collection<int, Resource>  $resources
     * @return array<string, array<string, int[]>>  ['2030-01-07' => ['10:00' => [3, 5], ...], ...]
     */
    public function slots(Shop $shop, Collection $resources, Service $service, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $result = [];
        foreach ($resources as $resource) {
            foreach ($this->resourceSlots($shop, $resource, $service, $from, $to) as $date => $times) {
                foreach ($times as $time) {
                    $result[$date][$time][] = $resource->id;
                }
            }
        }

        ksort($result);
        foreach ($result as &$times) {
            ksort($times);
        }

        return $result;
    }

    // 이 시각에 이 resource 로 받을 수 있나 (저장 직전 확인. 겹침은 저장할 때 잠금으로 한 번 더 확인한다)
    public function isBookable(Shop $shop, Resource $resource, Service $service, CarbonImmutable $start): bool
    {
        $day = $start->startOfDay();
        $times = $this->resourceSlots($shop, $resource, $service, $day, $day)[$day->format('Y-m-d')] ?? [];

        return in_array($start->format('H:i'), $times, true);
    }

    // 고객이 신청할 수 있는 그룹 일정 (취소 안 됨 + 예약 기간 안 + 남은 자리 계산)
    public function upcomingSessions(Shop $shop, Service $service): Collection
    {
        return $shop->classSessions()
            ->active()
            ->where('service_id', $service->id)
            ->where('start_at', '>=', $this->earliest())
            ->where('start_at', '<', $this->lastDay()->addDay())
            ->whereHas('resource', fn ($q) => $q->where('is_active', true))
            ->with('resource')
            ->withSum(['reservations as taken' => fn ($q) => $q->occupying()], 'party_size')
            ->orderBy('start_at')
            ->get();
    }

    /**
     * 그날 일하는 구간들: 특별 근무가 있으면 그것, 없으면 매주 근무 - 쉬는 시간. 그다음 휴무 구간을 뺀다
     *
     * @param  array{on:bool,start:?string,end:?string,break_start:?string,break_end:?string}  $weekly
     * @param  Collection<int, \App\Models\ResourceSchedule>  $dated  그날의 특정 날짜 일정
     * @return array<int, array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    public function workingRanges(array $weekly, Collection $dated, CarbonImmutable $day): array
    {
        $at = fn (string $hm) => $day->setTimeFromTimeString($hm);
        $special = $dated->where('type', 'work');

        if ($special->isNotEmpty()) {
            $ranges = $special->map(fn ($s) => [$at($s->start_time), $at($s->end_time)])->values()->all();
        } elseif ($weekly['on']) {
            $ranges = [[$at($weekly['start']), $at($weekly['end'])]];
            if ($weekly['break_start'] && $weekly['break_end']) {
                $ranges = $this->subtract($ranges, $at($weekly['break_start']), $at($weekly['break_end']));
            }
        } else {
            return [];
        }

        foreach ($dated->where('type', 'off') as $off) {
            if (! $off->start_time) {
                return [];      // 하루 종일 휴무
            }
            $ranges = $this->subtract($ranges, $at($off->start_time), $at($off->end_time));
        }

        return $ranges;
    }

    // ── 계산 ─────────────────────────────────────────────

    /** @return array<string, string[]> ['2030-01-07' => ['10:00', '10:30', ...]] */
    private function resourceSlots(Shop $shop, Resource $resource, Service $service, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $until = $to->startOfDay()->addDay();
        $unit = $shop->slot_unit;
        $earliest = $this->earliest();

        // 기간 안의 일정·예약·그룹 일정을 한 번에 불러 두고 메모리에서 계산 (날짜마다 쿼리하지 않음)
        $weekly = $this->schedules->weekly($resource);
        $dated = $resource->schedules()->whereNotNull('date')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->groupBy(fn ($s) => $s->date->format('Y-m-d'));

        $busy = Reservation::where('resource_id', $resource->id)->individual()->occupying()
            ->where('start_at', '<', $until->addDay())->where('occupied_until', '>', $from)
            ->get(['start_at', 'occupied_until'])
            ->map(fn ($r) => [$r->start_at->getTimestamp(), $r->occupied_until->getTimestamp()])->all();

        $blocked = ClassSession::where('resource_id', $resource->id)->active()
            ->where('start_at', '<', $until->addDay())->where('occupied_until', '>', $from)
            ->get(['start_at', 'occupied_until'])
            ->map(fn ($s) => [$s->start_at->getTimestamp(), $s->occupied_until->getTimestamp()])->all();

        $result = [];
        for ($day = $from; $day < $until; $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $ranges = $this->workingRanges($weekly[$day->dayOfWeek], $dated->get($key, collect()), $day);

            foreach ($ranges as [$open, $close]) {
                for ($t = $open; $t->addMinutes($service->duration_min) <= $close; $t = $t->addMinutes($unit)) {
                    if ($t < $earliest) {
                        continue;
                    }
                    $start = $t->getTimestamp();
                    $occupied = $t->addMinutes($service->duration_min + $service->buffer_min)->getTimestamp();

                    $overlaps = fn (array $b) => $b[0] < $occupied && $b[1] > $start;
                    if (count(array_filter($busy, $overlaps)) >= $resource->capacity) {
                        continue;
                    }
                    if (array_filter($blocked, $overlaps)) {
                        continue;
                    }

                    $result[$key][] = $t->format('H:i');
                }
            }
        }

        return $result;
    }

    // 구간들에서 [cutFrom, cutTo) 를 잘라낸다
    private function subtract(array $ranges, CarbonImmutable $cutFrom, CarbonImmutable $cutTo): array
    {
        $out = [];
        foreach ($ranges as [$a, $b]) {
            if ($cutTo <= $a || $cutFrom >= $b) {
                $out[] = [$a, $b];

                continue;
            }
            if ($cutFrom > $a) {
                $out[] = [$a, $cutFrom];
            }
            if ($cutTo < $b) {
                $out[] = [$cutTo, $b];
            }
        }

        return $out;
    }
}
