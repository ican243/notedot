<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\Staff;
use App\Services\Payment\GatewayPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

// 결제(보증금): 시작 → PG 확인 → 예약 확정 / 시간 초과 → 예약 자동 취소 / 취소 규정에 따른 환불
// 금액은 언제나 서버가 정하고, 결제 결과는 PG 에 직접 물어본 것만 믿는다. 결제 행을 잠그고 처리해서 두 번 처리되지 않는다
// 잠금 순서: 결제 → 예약
class PaymentService
{
    public function __construct(
        private PaymentGateway $gateway,
        private ReservationService $reservations,
        private AuditLogger $audit,
    ) {}

    // 보증금 결제 대기 만들기. 금액 = 예약에 저장된 보증금 (화면이 보낸 값이 아님)
    public function start(Reservation $reservation): Payment
    {
        return $reservation->payments()->create([
            'shop_id' => $reservation->shop_id,
            'customer_id' => $reservation->customer_id,
            'purpose' => 'deposit',
            'payment_key' => 'nd'.strtolower((string) Str::ulid()),
            'provider' => $this->gateway->name(),
            'amount' => $reservation->deposit,
            'status' => 'ready',
        ]);
    }

    // PG 에 물어보고 결과 반영 (결제창이 끝났을 때, 웹훅이 왔을 때). 몇 번 불러도 결과가 같다
    public function confirm(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = $this->lock($payment);
            if ($payment->status === 'ready') {
                $this->apply($payment, $this->gateway->fetch($payment));
            }

            return $payment;
        });
    }

    // 결제 대기 시간이 지난 것 정리 (스케줄러가 1분마다). 처리한 개수
    public function expireOverdue(): int
    {
        $ids = Payment::where('status', 'ready')
            ->where('created_at', '<=', now()->subMinutes(config('payment.hold_minutes')))
            ->pluck('id');

        foreach ($ids as $id) {
            try {
                $this->expire(Payment::findOrFail($id));
            } catch (Throwable $e) {
                // PG 조회가 안 되면 이번에는 건너뛰고 다음 분에 다시
                Log::warning('payment expire failed', ['payment_id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return $ids->count();
    }

    // 마지막으로 PG 에 한 번 더 물어보고(방금 냈을 수도 있다), 그래도 안 냈으면 실패 + 예약 취소
    public function expire(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = $this->lock($payment);
            if ($payment->status !== 'ready') {
                return $payment;
            }

            $this->apply($payment, $this->gateway->fetch($payment));
            if ($payment->status === 'ready') {
                $this->fail($payment, '결제 시간 초과');
            }

            return $payment;
        });
    }

    // 대표가 환불 (예약 취소 때 또는 따로)
    public function refund(Payment $payment, ?Staff $by, int $amount, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $by, $amount, $reason) {
            $payment = $this->lock($payment);
            $max = $payment->refundable();
            if ($amount < 1 || $amount > $max) {
                throw ValidationException::withMessages(['refund_amount' => $max
                    ? '환불 금액은 1원 ~ '.number_format($max).'원 사이여야 합니다.'
                    : '환불할 수 있는 결제가 없습니다.']);
            }

            try {
                $this->gateway->refund($payment, $amount, $reason);
            } catch (Throwable $e) {
                throw ValidationException::withMessages(['refund_amount' => '환불 요청이 실패했습니다. '.$e->getMessage()]);
            }
            $this->markRefunded($payment, $amount, $reason, $by);

            return $payment;
        });
    }

    /**
     * 취소 규정에 따른 환불 제안: 예약 시작까지 남은 시간으로 구간을 찾는다
     *
     * @return array{percent:int, amount:int}
     */
    public function suggestRefund(Payment $payment, ?CarbonInterface $now = null): array
    {
        $percent = $this->refundPercent($payment->shop, $payment->reservation->start_at, $now ?? now());

        return ['percent' => $percent, 'amount' => intdiv($payment->refundable() * $percent, 100)];
    }

    public function refundPercent(Shop $shop, CarbonInterface $start, CarbonInterface $now): int
    {
        $hoursLeft = $now->diffInMinutes($start, false) / 60;   // 지난 예약이면 음수
        $tiers = collect($this->policy($shop)['tiers'])->sortByDesc('hours');

        foreach ($tiers as $tier) {
            if ($hoursLeft >= $tier['hours']) {
                return (int) $tier['percent'];
            }
        }

        return 0;
    }

    public function policy(Shop $shop): array
    {
        return ! empty($shop->cancel_policy['tiers']) ? $shop->cancel_policy : config('payment.default_cancel_policy');
    }

    // 손님 화면에 보여 줄 취소 규정 문장 (예: "예약 24시간 전까지 취소: 보증금 100% 환불")
    public function policyLines(Shop $shop): array
    {
        $lines = collect($this->policy($shop)['tiers'])->sortByDesc('hours')
            ->map(fn ($t) => "예약 {$t['hours']}시간 전까지 취소: 보증금 {$t['percent']}% 환불")
            ->values()->all();
        $lines[] = '그 이후 취소·노쇼: 보증금 환불 없음';

        return $lines;
    }

    // 예약 창에 보여 줄 결제 정보 (결제가 없으면 null)
    public function summaryFor(Reservation $reservation): ?array
    {
        $payment = $reservation->payment;
        if (! $payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'label' => Payment::STATUSES[$payment->status],
            'amount' => $payment->amount,
            'refunded' => $payment->refunded_amount,
            'refundable' => $payment->refundable(),
            'method' => $payment->method,
            'paid_at' => $payment->paid_at?->format('m/d H:i'),
            'due_at' => $payment->status === 'ready' ? $payment->dueAt()->format('H:i') : null,
            'failed_reason' => $payment->failed_reason,
            'receipt_url' => $payment->receipt_url,
            'suggest' => $payment->refundable() ? $this->suggestRefund($payment) : null,
        ];
    }

    // ── 안쪽 ─────────────────────────────────────

    // PG 가 알려 준 상태를 결제·예약에 반영 (결제 행은 잠긴 상태)
    private function apply(Payment $payment, GatewayPayment $result): void
    {
        match ($result->status) {
            GatewayPayment::PAID => $this->paid($payment, $result),
            GatewayPayment::FAILED => $this->fail($payment, $result->failReason ?: '결제에 실패했습니다.'),
            GatewayPayment::CANCELED => $this->fail($payment, '결제가 취소되었습니다.'),
            default => null,   // 아직 진행 중
        };
    }

    private function paid(Payment $payment, GatewayPayment $result): void
    {
        // 금액이 다르면(위조·오류) 받은 돈을 바로 돌려주고 실패로
        if ($result->amount !== $payment->amount) {
            try {
                $this->gateway->refund($payment, $result->amount, '결제 금액 불일치');
            } catch (Throwable $e) {
                Log::error('payment mismatch refund failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
            $this->fail($payment, '결제 금액이 맞지 않아 취소했습니다.');

            return;
        }

        $payment->update([
            'status' => 'paid',
            'method' => $result->method,
            'pg_tx_id' => $result->pgTxId,
            'receipt_url' => $result->receiptUrl,
            'paid_at' => now(),
        ]);
        $reservation = $payment->reservation;
        $this->audit->log($reservation, 'paid', null, ['deposit' => [null, $payment->amount]]);

        if ($reservation->status === 'pending') {
            $this->reservations->changeStatus($reservation, null, 'confirmed');
        } elseif ($reservation->status === 'canceled') {
            // 시간 초과로 이미 취소된 뒤에 결제가 들어옴 → 자리가 없으니 전액 돌려준다
            try {
                $this->gateway->refund($payment, $payment->amount, '예약 취소 후 결제되어 자동 환불');
                $this->markRefunded($payment, $payment->amount, '예약 취소 후 결제되어 자동 환불', null);
            } catch (Throwable $e) {
                Log::error('late payment refund failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }
    }

    // 결제 실패: 아직 결제를 기다리던 예약이면 취소해서 자리를 푼다
    private function fail(Payment $payment, string $reason): void
    {
        $payment->update(['status' => 'failed', 'failed_reason' => mb_substr($reason, 0, 200)]);

        $reservation = $payment->reservation;
        if ($reservation->status === 'pending') {
            $this->reservations->changeStatus($reservation, null, 'canceled', $reason);
        }
    }

    private function markRefunded(Payment $payment, int $amount, string $reason, ?Staff $by): void
    {
        $refunded = $payment->refunded_amount + $amount;
        $payment->update([
            'refunded_amount' => $refunded,
            'status' => $refunded >= $payment->amount ? 'canceled' : 'partial_refunded',
            'refunded_at' => now(),
            'refund_reason' => mb_substr($reason, 0, 200),
        ]);
        $this->audit->log($payment->reservation, 'refunded', $by, ['refund' => [null, $amount]]);
    }

    private function lock(Payment $payment): Payment
    {
        return Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
    }
}
