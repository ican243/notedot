<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

// 직원 계정 폼 검사
class StaffRequest extends AdminRequest
{
    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => [
                'required', 'email', 'max:191',
                Rule::unique('staffs')->where('shop_id', $this->shop()->id)->ignore($staff),
            ],
            'role' => ['required', Rule::in(['owner', 'staff'])],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'resource_id' => [
                'nullable', 'integer',
                Rule::exists('resources', 'id')
                    ->where('shop_id', $this->shop()->id)
                    ->where('type', 'person')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
