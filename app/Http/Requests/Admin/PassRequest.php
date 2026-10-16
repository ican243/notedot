<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

// 회원권 발급 폼 검사
class PassRequest extends AdminRequest
{
    public function rules(): array
    {
        $count = $this->input('type') === 'count';

        return [
            'type' => ['required', Rule::in(['amount', 'count'])],
            'name' => ['required', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'total' => ['required', 'integer', 'min:1', $count ? 'max:1000' : 'max:100000000'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('shop_id', $this->shop()->id)],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'memo' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'total' => $this->input('type') === 'count' ? '횟수' : '충전 금액',
            'price' => '받은 금액',
            'expires_on' => '만료일',
        ];
    }

    public function messages(): array
    {
        return ['expires_on.after_or_equal' => '만료일은 오늘 이후여야 합니다.'];
    }
}
