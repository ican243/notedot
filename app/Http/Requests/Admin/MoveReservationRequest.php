<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;

// 드래그 이동: 새 시작 시각 (캘린더가 보내는 ISO 형식, 예 2026-10-20T14:00:00+09:00)
class MoveReservationRequest extends AdminRequest
{
    public function rules(): array
    {
        return ['start' => ['required', 'date']];
    }

    // 브라우저 시간대가 달라도 매장 시간(Asia/Seoul)으로 맞춘다
    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('start'))->setTimezone(config('app.timezone'))->startOfMinute();
    }
}
