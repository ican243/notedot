<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

// service(상품) 폼 검사. 예약 방식은 매장 업종에서 허용한 것만
class ServiceRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'booking_type' => ['required', Rule::in($this->shop()->bookingTypes())],
            'duration_min' => ['required', 'integer', 'min:5', 'max:1440'],
            'buffer_min' => ['required', 'integer', 'min:0', 'max:240'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'deposit' => ['required', 'integer', 'min:0', 'lte:price'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'resource_ids' => ['array'],
            'resource_ids.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '이름', 'duration_min' => '소요 시간', 'buffer_min' => '정리 시간',
            'capacity' => '정원', 'price' => '가격', 'deposit' => '보증금',
        ];
    }
}
