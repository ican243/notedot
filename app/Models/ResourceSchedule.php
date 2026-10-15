<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 근무표 한 줄 (매주 반복 또는 특정 날짜)
class ResourceSchedule extends Model
{
    public const TYPES = ['work' => '근무', 'break' => '쉬는 시간', 'off' => '휴무'];

    protected $fillable = ['shop_id', 'type', 'weekday', 'date', 'start_time', 'end_time', 'memo'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'date' => 'date',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
