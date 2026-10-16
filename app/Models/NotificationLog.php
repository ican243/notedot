<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 알림 한 건 (확정·리마인드·취소). 상태: pending 대기 → sent 보냄 / failed 실패 / skipped 건너뜀
class NotificationLog extends Model
{
    public const TYPES = ['confirmed' => '예약 확정', 'reminder' => '전날 리마인드', 'canceled' => '예약 취소'];

    public const CHANNELS = ['alimtalk' => '알림톡', 'sms' => '문자', 'log' => '기록만'];

    public const STATUSES = ['pending' => '대기', 'sent' => '보냄', 'failed' => '실패', 'skipped' => '건너뜀'];

    protected $fillable = [
        'shop_id', 'customer_id', 'reservation_id', 'type', 'channel', 'to_phone', 'message',
        'status', 'dedupe_key', 'provider_message_id', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withTrashed();
    }

    // 화면 표시용 010-****-1234
    public function maskedPhone(): string
    {
        $p = preg_replace('/\D/', '', $this->to_phone);

        return strlen($p) >= 10 ? substr($p, 0, 3).'-****-'.substr($p, -4) : $p;
    }
}
