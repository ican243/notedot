<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\ResourceSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// 근무표: 매주 반복 일정 저장·조회, 특정 날짜 예외(휴무/특별 근무)
class ScheduleService
{
    // weekday 숫자(0=일) ↔ 영업시간 키, 화면 이름
    public const WEEKDAYS = [
        1 => ['key' => 'mon', 'label' => '월'],
        2 => ['key' => 'tue', 'label' => '화'],
        3 => ['key' => 'wed', 'label' => '수'],
        4 => ['key' => 'thu', 'label' => '목'],
        5 => ['key' => 'fri', 'label' => '금'],
        6 => ['key' => 'sat', 'label' => '토'],
        0 => ['key' => 'sun', 'label' => '일'],
    ];

    // 새 resource 의 기본 근무표 = 매장 영업시간
    public function copyShopHours(Resource $resource): void
    {
        $hours = $resource->shop->business_hours ?? [];
        $days = [];

        foreach (self::WEEKDAYS as $weekday => $day) {
            $h = $hours[$day['key']] ?? null;
            $days[$weekday] = [
                'on' => (bool) $h,
                'start' => $h['open'] ?? null,
                'end' => $h['close'] ?? null,
            ];
        }

        $this->saveWeekly($resource, $days);
    }

    /**
     * 요일별 반복 일정을 통째로 바꾼다 (기존 반복 일정 삭제 후 다시 저장)
     *
     * @param  array<int, array{on:bool,start:?string,end:?string,break_start?:?string,break_end?:?string}>  $days
     */
    public function saveWeekly(Resource $resource, array $days): void
    {
        DB::transaction(function () use ($resource, $days) {
            $resource->schedules()->whereNotNull('weekday')->delete();

            foreach ($days as $weekday => $d) {
                if (empty($d['on'])) {
                    continue;
                }

                $this->addRow($resource, 'work', $d['start'], $d['end'], weekday: $weekday);

                if (! empty($d['break_start']) && ! empty($d['break_end'])) {
                    $this->addRow($resource, 'break', $d['break_start'], $d['break_end'], weekday: $weekday);
                }
            }
        });
    }

    // 화면용: weekday => [on, start, end, break_start, break_end]
    public function weekly(Resource $resource): array
    {
        $rows = $resource->schedules()->whereNotNull('weekday')->get()->groupBy('weekday');
        $result = [];

        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $work = $rows->get($weekday)?->firstWhere('type', 'work');
            $break = $rows->get($weekday)?->firstWhere('type', 'break');

            $result[$weekday] = [
                'on' => (bool) $work,
                'start' => $this->hm($work?->start_time),
                'end' => $this->hm($work?->end_time),
                'break_start' => $this->hm($break?->start_time),
                'break_end' => $this->hm($break?->end_time),
            ];
        }

        return $result;
    }

    // 오늘 이후 특정 날짜 일정
    public function upcomingExceptions(Resource $resource): Collection
    {
        return $resource->schedules()
            ->whereNotNull('date')
            ->where('date', '>=', today())
            ->orderBy('date')->orderBy('start_time')
            ->get();
    }

    /** @param array{date:string,type:string,start_time:?string,end_time:?string,memo:?string} $data */
    public function addException(Resource $resource, array $data): ResourceSchedule
    {
        $allDay = $data['type'] === 'off' && empty($data['start_time']);

        return $this->addRow(
            $resource,
            $data['type'],
            $allDay ? null : $data['start_time'],
            $allDay ? null : $data['end_time'],
            date: $data['date'],
            memo: $data['memo'] ?? null,
        );
    }

    private function addRow(Resource $resource, string $type, ?string $start, ?string $end, ?int $weekday = null, ?string $date = null, ?string $memo = null): ResourceSchedule
    {
        return $resource->schedules()->create([
            'shop_id' => $resource->shop_id,
            'type' => $type,
            'weekday' => $weekday,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'memo' => $memo,
        ]);
    }

    // DB 의 "10:00:00" → 화면 "10:00"
    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
