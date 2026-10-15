<?php

namespace App\Http\Requests\Admin;

use App\Models\Resource;
use Illuminate\Validation\Rule;

// resource(담당자/공간) 폼 검사
class ResourceRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(Resource::TYPES))],
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => '이름', 'color' => '색상', 'capacity' => '정원'];
    }
}
