<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 예약 상품
class Service extends Model
{
    use SoftDeletes;

    // 예약 방식 화면 이름 (업종 단어를 쓰지 않는 공통 표현)
    public const BOOKING_TYPES = [
        'one_to_one' => '1:1 예약',
        'group' => '그룹 (정원제)',
        'space' => '공간 대여',
    ];

    protected $fillable = [
        'category', 'name', 'description', 'booking_type', 'duration_min',
        'buffer_min', 'capacity', 'price', 'deposit', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_min' => 'integer',
            'buffer_min' => 'integer',
            'capacity' => 'integer',
            'price' => 'integer',
            'deposit' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // 이 상품을 할 수 있는 resource 들
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class);
    }
}
