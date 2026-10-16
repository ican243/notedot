<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

// 결제 대기 시간이 지난 보증금 결제 정리 → 예약 자동 취소 (스케줄러가 1분마다)
class ExpirePayments extends Command
{
    protected $signature = 'payments:expire';

    protected $description = '결제 대기 시간이 지난 보증금 결제를 실패 처리하고 예약을 취소합니다';

    public function handle(PaymentService $payments): int
    {
        $count = $payments->expireOverdue();
        if ($count) {
            $this->info("결제 대기 {$count}건 정리");
        }

        return self::SUCCESS;
    }
}
