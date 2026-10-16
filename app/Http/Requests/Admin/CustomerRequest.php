<?php

namespace App\Http\Requests\Admin;

// 고객 정보 수정 (이름, 메모)
class CustomerRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
