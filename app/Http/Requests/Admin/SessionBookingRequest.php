<?php

namespace App\Http\Requests\Admin;

use App\Models\Reservation;
use Illuminate\Validation\Rule;

// 그룹 일정 신청: 고객(기존 또는 새로) + 인원
class SessionBookingRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('shop_id', $this->shop()->id)->whereNull('deleted_at')],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:50'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'regex:/^0\d{1,2}-?\d{3,4}-?\d{4}$/'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'channel' => ['nullable', Rule::in(array_keys(Reservation::CHANNELS))],
            'customer_request' => ['nullable', 'string', 'max:500'],
            'staff_memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['customer_name' => '고객 이름', 'customer_phone' => '전화번호', 'party_size' => '인원'];
    }

    public function messages(): array
    {
        return [
            'customer_name.required_without' => '고객을 고르거나 새 고객 이름을 입력하세요.',
            'customer_phone.required_without' => '새 고객 전화번호를 입력하세요.',
            'customer_phone.regex' => '전화번호 형식이 올바르지 않습니다.',
        ];
    }
}
