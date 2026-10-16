<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// 포트원 V2 연결
// - 단건 조회 GET  /payments/{paymentId}         (Authorization: PortOne {API 시크릿})
// - 결제 취소 POST /payments/{paymentId}/cancel  {amount, reason}
// - 웹훅 서명: Standard Webhooks (webhook-id · webhook-timestamp · webhook-signature, HMAC-SHA256)
class PortOneGateway implements PaymentGateway
{
    private const WEBHOOK_TOLERANCE = 300;   // 웹훅 시각이 5분 넘게 차이 나면 거절 (재전송 공격 방지)

    public function __construct(private array $cfg) {}

    public function name(): string
    {
        return 'portone';
    }

    public function clientConfig(Payment $payment): array
    {
        return [
            'driver' => 'portone',
            'storeId' => $this->cfg['store_id'],
            'channelKey' => $this->cfg['channel_key'],
        ];
    }

    public function fetch(Payment $payment): GatewayPayment
    {
        $res = $this->http()->get('/payments/'.rawurlencode($payment->payment_key));

        if ($res->status() === 404) {
            return new GatewayPayment(GatewayPayment::PENDING);   // 결제창을 열기만 하고 아직 안 낸 경우
        }
        if ($res->failed()) {
            throw new RuntimeException('결제 조회 실패 ('.$res->status().')');
        }

        $p = $res->json();
        // 다른 상점의 결제 번호를 끼워 넣는 일 방지
        if (($p['storeId'] ?? null) !== $this->cfg['store_id']) {
            return new GatewayPayment(GatewayPayment::FAILED, failReason: '상점 정보가 맞지 않습니다.');
        }

        $status = match ($p['status'] ?? '') {
            'PAID' => GatewayPayment::PAID,
            'FAILED' => GatewayPayment::FAILED,
            'CANCELLED', 'PARTIAL_CANCELLED' => GatewayPayment::CANCELED,
            default => GatewayPayment::PENDING,   // READY, PENDING, VIRTUAL_ACCOUNT_ISSUED
        };

        return new GatewayPayment(
            $status,
            (int) ($p['amount']['total'] ?? 0),
            isset($p['method']['type']) ? substr($p['method']['type'], 0, 30) : null,
            $p['pgTxId'] ?? ($p['transactionId'] ?? null),
            $p['receiptUrl'] ?? null,
            $p['failure']['reason'] ?? ($p['failure']['message'] ?? null),
        );
    }

    public function refund(Payment $payment, int $amount, string $reason): void
    {
        $res = $this->http()->post('/payments/'.rawurlencode($payment->payment_key).'/cancel', [
            'amount' => $amount,
            'reason' => $reason,
        ]);

        if ($res->failed()) {
            throw new RuntimeException('환불 요청 실패: '.($res->json('message') ?? $res->status()));
        }
    }

    public function webhookPaymentKey(Request $request): ?string
    {
        $id = (string) $request->header('webhook-id');
        $ts = (string) $request->header('webhook-timestamp');
        $sigs = (string) $request->header('webhook-signature');
        $secret = (string) ($this->cfg['webhook_secret'] ?? '');

        if ($id === '' || ! ctype_digit($ts) || $sigs === '' || $secret === '' || abs(time() - (int) $ts) > self::WEBHOOK_TOLERANCE) {
            return null;
        }

        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false) {
            return null;
        }
        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$ts}.".$request->getContent(), $key, true));

        // 헤더에는 "v1,서명 v1,서명" 처럼 여러 개가 올 수 있다
        foreach (explode(' ', $sigs) as $sig) {
            [$version, $value] = array_pad(explode(',', $sig, 2), 2, '');
            if ($version === 'v1' && hash_equals($expected, $value)) {
                $paymentId = $request->input('data.paymentId');

                return is_string($paymentId) ? $paymentId : null;
            }
        }

        return null;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->cfg['api_url'], '/'))
            ->withHeaders(['Authorization' => 'PortOne '.$this->cfg['api_secret']])
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10);
    }
}
