<?php

namespace App\Http\Requests\Admin;

use App\Models\Shop;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// 매장 설정 폼 검사
class ShopSettingRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'industry' => ['required', Rule::in(array_keys(config('industries')))],
            'slot_unit' => ['required', 'integer', Rule::in([10, 15, 20, 30, 60])],
            'theme' => ['required', Rule::in(['auto', 'spring', 'summer', 'autumn', 'winter'])],
            'labels.resource' => ['nullable', 'string', 'max:20'],
            'labels.service' => ['nullable', 'string', 'max:20'],
            'labels.customer' => ['nullable', 'string', 'max:20'],
            'hours.*.closed' => ['boolean'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
        ];
    }

    // 영업하는 요일은 여는 시각 < 닫는 시각
    public function after(): array
    {
        return [function (Validator $v) {
            foreach (Shop::DAY_KEYS as $key => $label) {
                $h = $this->input("hours.$key", []);
                if (! empty($h['closed'])) {
                    continue;
                }
                if (empty($h['open']) || empty($h['close']) || $h['open'] >= $h['close']) {
                    $v->errors()->add("hours.$key", "{$label}요일 영업시간을 확인하세요.");
                }
            }
        }];
    }

    // 저장할 모양으로 정리
    public function toShopData(): array
    {
        $hours = [];
        foreach (array_keys(Shop::DAY_KEYS) as $key) {
            $h = $this->input("hours.$key", []);
            $hours[$key] = ! empty($h['closed']) ? null : ['open' => $h['open'], 'close' => $h['close']];
        }

        return [
            'name' => $this->input('name'),
            'industry' => $this->input('industry'),
            'slot_unit' => (int) $this->input('slot_unit'),
            'theme' => $this->input('theme'),
            'labels' => array_filter($this->input('labels', []), fn ($v) => filled($v)) ?: null,
            'business_hours' => $hours,
        ];
    }
}
