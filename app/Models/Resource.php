<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

// 예약 대상 (사람 또는 공간)
class Resource extends Model
{
    use SoftDeletes;

    public const TYPES = ['person' => '사람', 'space' => '공간'];

    protected $fillable = ['type', 'name', 'color', 'capacity', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // 이 resource 가 할 수 있는 상품들
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ResourceSchedule::class);
    }

    // 연결된 직원 로그인 계정 (공간은 없음)
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }
}
