<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

// 고객 예약 폼 검사. 시간(date+start_time) 또는 그룹 일정(class_session_id) 중 하나
class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'resource_id' => ['nullable', 'integer'],
            'class_session_id' => ['nullable', 'integer'],
            'date' => ['required_without:class_session_id', 'nullable', 'date_format:Y-m-d'],
            'start_time' => ['required_without:class_session_id', 'nullable', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'max:50'],
            'customer_phone' => ['required', 'regex:/^01\d-?\d{3,4}-?\d{4}$/'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:20'],
            'customer_request' => ['nullable', 'string', 'max:500'],
            'privacy' => ['accepted'],
            'marketing' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => '이름',
            'customer_phone' => '휴대폰 번호',
            'party_size' => '인원',
            'start_time' => '시간',
            'customer_request' => '요청사항',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_phone.regex' => '휴대폰 번호를 확인해 주세요.',
            'privacy.accepted' => '개인정보 수집·이용에 동의해 주세요.',
            'date.required_without' => '날짜를 골라 주세요.',
            'start_time.required_without' => '시간을 골라 주세요.',
        ];
    }
}
