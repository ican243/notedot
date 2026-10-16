<?php

namespace App\Http\Requests\Admin;

// 회원권 잔액·포인트 직접 조정 (+ 더하기, - 빼기, 사유 필수)
class BalanceAdjustRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'not_in:0', 'between:-100000000,100000000'],
            'memo' => ['required', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => '조정할 값', 'memo' => '사유'];
    }

    public function messages(): array
    {
        return ['amount.not_in' => '0 이 아닌 값을 입력하세요. (빼려면 -1 처럼)'];
    }
}
