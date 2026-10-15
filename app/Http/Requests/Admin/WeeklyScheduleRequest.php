<?php

namespace App\Http\Requests\Admin;

use App\Services\ScheduleService;
use Illuminate\Validation\Validator;

// 요일별 반복 근무표 폼 검사
class WeeklyScheduleRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'days' => ['array'],
            'days.*.on' => ['boolean'],
            'days.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.end' => ['nullable', 'date_format:H:i'],
            'days.*.break_start' => ['nullable', 'date_format:H:i'],
            'days.*.break_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    // 근무 시작 < 끝, 쉬는 시간은 근무 시간 안
    public function after(): array
    {
        return [function (Validator $v) {
            foreach (ScheduleService::WEEKDAYS as $wd => $day) {
                $d = $this->input("days.$wd", []);
                if (empty($d['on'])) {
                    continue;
                }
                if (empty($d['start']) || empty($d['end']) || $d['start'] >= $d['end']) {
                    $v->errors()->add("days.$wd", "{$day['label']}요일 근무 시간을 확인하세요.");

                    continue;
                }
                $bs = $d['break_start'] ?? null;
                $be = $d['break_end'] ?? null;
                if (($bs || $be) && (! $bs || ! $be || $bs >= $be || $bs < $d['start'] || $be > $d['end'])) {
                    $v->errors()->add("days.$wd", "{$day['label']}요일 쉬는 시간은 근무 시간 안이어야 합니다.");
                }
            }
        }];
    }

    // 체크 안 된 요일까지 채운 모양
    public function days(): array
    {
        $days = [];
        foreach (array_keys(ScheduleService::WEEKDAYS) as $wd) {
            $d = $this->input("days.$wd", []);
            $days[$wd] = [
                'on' => ! empty($d['on']),
                'start' => $d['start'] ?? null,
                'end' => $d['end'] ?? null,
                'break_start' => $d['break_start'] ?? null,
                'break_end' => $d['break_end'] ?? null,
            ];
        }

        return $days;
    }
}
