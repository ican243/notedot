<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// 데모 결제: 실제 돈이 오가지 않는다. 가짜 결제창에서 "승인/실패"를 누르면 그 결과를 캐시에 적어 두고, fetch 가 그것을 PG 응답처럼 돌려준다
class DemoGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'demo';
    }

    public function clientConfig(Payment $payment): array
    {
        return ['driver' => 'demo'];
    }

    // 가짜 결제창의 결과 기록 (paid | failed)
    public function simulate(Payment $payment, string $result): void
    {
        Cache::put($this->key($payment), $result, now()->addDay());
    }

    public function fetch(Payment $payment): GatewayPayment
    {
        return match (Cache::get($this->key($payment))) {
            'paid' => new GatewayPayment(GatewayPayment::PAID, $payment->amount, 'demo_card', 'demo-'.$payment->payment_key),
            'failed' => new GatewayPayment(GatewayPayment::FAILED, failReason: '데모 결제창에서 실패를 골랐습니다.'),
            default => new GatewayPayment(GatewayPayment::PENDING),
        };
    }

    public function refund(Payment $payment, int $amount, string $reason): void
    {
        // 데모: 돌려줄 실제 돈이 없다
    }

    public function webhookPaymentKey(Request $request): ?string
    {
        return null;   // 데모에는 웹훅이 없다
    }

    private function key(Payment $payment): string
    {
        return 'demo-payment:'.$payment->payment_key;
    }
}
