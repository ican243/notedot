<?php

namespace App\Http\Requests\Admin;

use App\Models\Reservation;
use Illuminate\Validation\Rule;

// 예약 만들기·수정 폼 검사. 고객은 기존 고객(customer_id) 또는 새 고객(이름+전화번호) 중 하나
class ReservationRequest extends AdminRequest
{
    public function rules(): array
    {
        $shopId = $this->shop()->id;

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('shop_id', $shopId)->whereNull('deleted_at')],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:50'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'regex:/^0\d{1,2}-?\d{3,4}-?\d{4}$/'],
            'resource_id' => ['required', 'integer'],
            'service_ids' => ['required', 'array', 'min:1', 'max:10'],
            'service_ids.*' => ['integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'channel' => ['nullable', Rule::in(array_keys(Reservation::CHANNELS))],
            'customer_request' => ['nullable', 'string', 'max:500'],
            'staff_memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => '고객 이름',
            'customer_phone' => '전화번호',
            'resource_id' => term('resource'),
            'service_ids' => term('service'),
            'start_time' => '시작 시각',
            'party_size' => '인원',
            'channel' => '예약 경로',
        ];
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
