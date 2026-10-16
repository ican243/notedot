<?php

namespace App\Http\Requests\Admin;

// 그룹 일정 만들기 폼 검사 (반복: 요일 + 몇 주)
class ClassSessionRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'resource_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'memo' => ['nullable', 'string', 'max:500'],
            'repeat_days' => ['nullable', 'array', 'max:7'],
            'repeat_days.*' => ['integer', 'between:0,6'],
            'repeat_weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function attributes(): array
    {
        return [
            'service_id' => term('service'),
            'resource_id' => term('resource'),
            'start_time' => '시작 시각',
            'capacity' => '정원',
            'repeat_weeks' => '반복 주',
        ];
    }
}
