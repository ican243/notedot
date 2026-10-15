<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

// 특정 날짜 일정(휴무 / 특별 근무) 폼 검사
class ScheduleExceptionRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'type' => ['required', Rule::in(['off', 'work'])],
            'start_time' => ['nullable', 'required_if:type,work', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:type,work', 'required_with:start_time', 'date_format:H:i', 'after:start_time'],
            'memo' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['date.after_or_equal' => '오늘 이후 날짜를 고르세요.'];
    }

    public function attributes(): array
    {
        return ['date' => '날짜', 'start_time' => '시작 시각', 'end_time' => '끝 시각'];
    }
}
