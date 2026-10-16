<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 회원권 거래 한 줄 (쌓기만 한다)
class PassTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['issue' => '발급', 'use' => '사용', 'restore' => '사용 취소', 'adjust' => '조정', 'cancel' => '권 취소'];

    protected $fillable = ['shop_id', 'pass_id', 'reservation_id', 'type', 'amount', 'balance_after', 'memo', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class)->withTrashed();
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
