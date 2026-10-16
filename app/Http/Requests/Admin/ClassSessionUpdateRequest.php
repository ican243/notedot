<?php

namespace App\Http\Requests\Admin;

// 그룹 일정 정원·메모 수정
class ClassSessionUpdateRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['capacity' => '정원'];
    }
}
