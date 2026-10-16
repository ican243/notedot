<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

// 예약
class Reservation extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'pending' => '대기',
        'confirmed' => '확정',
        'completed' => '완료',
        'canceled' => '취소',
        'no_show' => '노쇼',
    ];

    public const CHANNELS = ['phone' => '전화', 'walk_in' => '방문', 'online' => '온라인'];

    protected $fillable = [
        'customer_id', 'resource_id', 'class_session_id', 'start_at', 'end_at', 'occupied_until',
        'party_size', 'status', 'channel', 'total_price', 'deposit',
        'customer_request', 'staff_memo', 'created_by', 'canceled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'occupied_until' => 'datetime',
            'canceled_at' => 'datetime',
            'party_size' => 'integer',
            'total_price' => 'integer',
            'deposit' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class)->withTrashed();
    }

    // 그룹 일정 신청이면 그 일정 (1:1·공간 예약은 null)
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    // 자리를 차지하고 있는 예약 (취소만 자리를 비운다)
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // 가장 최근 결제 (보증금)
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function scopeOccupying(Builder $query): void
    {
        $query->where('status', '!=', 'canceled');
    }

    // 1:1·공간 예약만 (그룹 일정 신청 제외) — 그룹 신청은 일정 정원으로 따로 센다
    public function scopeIndividual(Builder $query): void
    {
        $query->whereNull('class_session_id');
    }

    // [from, to) 구간과 겹치는 예약: 기존.시작 < 새.끝 AND 기존.끝 > 새.시작
    public function scopeOverlapping(Builder $query, $from, $to): void
    {
        $query->where('start_at', '<', $to)->where('occupied_until', '>', $from);
    }
}
