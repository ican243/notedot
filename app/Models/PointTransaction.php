<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 포인트 거래 한 줄 (쌓기만 한다)
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['earn' => '적립', 'use' => '사용', 'revoke' => '적립 회수', 'adjust' => '조정'];

    protected $fillable = ['shop_id', 'customer_id', 'reservation_id', 'type', 'amount', 'balance_after', 'memo', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by')->withTrashed();
    }
}
