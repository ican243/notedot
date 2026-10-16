<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 그룹 일정 한 칸 (정원제). 신청은 reservations 에 class_session_id 로 연결된다
class ClassSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'service_id', 'resource_id', 'start_at', 'end_at', 'occupied_until',
        'capacity', 'memo', 'created_by', 'canceled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'occupied_until' => 'datetime',
            'canceled_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class)->withTrashed();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    // 취소되지 않은 일정
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('canceled_at');
    }

    // [from, to) 와 겹치는 일정 (Reservation::scopeOverlapping 과 같은 규칙)
    public function scopeOverlapping(Builder $query, $from, $to): void
    {
        $query->where('start_at', '<', $to)->where('occupied_until', '>', $from);
    }
}
