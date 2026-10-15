<?php

namespace App\Http\Requests\Admin;

use App\Models\Reservation;
use Illuminate\Validation\Rule;

// 상태 바꾸기 (취소면 사유 선택 입력)
class ReservationStatusRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Reservation::STATUSES))],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
