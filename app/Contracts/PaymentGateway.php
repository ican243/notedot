<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Services\Payment\GatewayPayment;
use Illuminate\Http\Request;

// 결제 대행사(PG) 연결. 포트원(PortOneGateway) 또는 데모(DemoGateway)
interface PaymentGateway
{
    public function name(): string;

    // 결제 화면(JavaScript)에 넘길 값
    public function clientConfig(Payment $payment): array;

    // PG 에 이 결제의 진짜 상태를 물어본다 (화면이 보낸 결과는 믿지 않는다)
    public function fetch(Payment $payment): GatewayPayment;

    // 환불(결제 취소). 실패하면 예외
    public function refund(Payment $payment, int $amount, string $reason): void;

    // 웹훅이 정말 PG 가 보낸 것인지 서명 확인 → 결제 번호. 아니면 null
    public function webhookPaymentKey(Request $request): ?string;
}
