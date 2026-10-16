<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 결제 한 건 (보증금). 상태: ready 결제 대기 → paid 완료 / failed 실패·시간 초과 → canceled 전액 환불 / partial_refunded 부분 환불
class Payment extends Model
{
    public const STATUSES = [
        'ready' => '결제 대기',
        'paid' => '결제 완료',
        'failed' => '결제 실패',
        'canceled' => '전액 환불',
        'partial_refunded' => '부분 환불',
    ];

    protected $fillable = [
        'shop_id', 'reservation_id', 'customer_id', 'purpose', 'payment_key', 'provider', 'amount', 'status',
        'method', 'pg_tx_id', 'receipt_url', 'paid_at', 'failed_reason', 'refunded_amount', 'refunded_at', 'refund_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // 결제 대기를 끝내야 하는 시각 (이때까지 안 내면 예약 자동 취소)
    public function dueAt(): CarbonInterface
    {
        return $this->created_at->copy()->addMinutes(config('payment.hold_minutes'));
    }

    // 아직 돌려줄 수 있는 금액
    public function refundable(): int
    {
        return in_array($this->status, ['paid', 'partial_refunded'], true) ? $this->amount - $this->refunded_amount : 0;
    }
}
