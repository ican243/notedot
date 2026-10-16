<?php

namespace App\Services\Payment;

// PG 가 알려 준 결제 상태 (PG 마다 모양이 달라서 우리 모양으로 맞춘 것)
final class GatewayPayment
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';     // 아직 결제 전·진행 중

    public const CANCELED = 'canceled';   // PG 쪽에서 이미 취소됨

    public function __construct(
        public readonly string $status,
        public readonly int $amount = 0,
        public readonly ?string $method = null,
        public readonly ?string $pgTxId = null,
        public readonly ?string $receiptUrl = null,
        public readonly ?string $failReason = null,
    ) {}
}
