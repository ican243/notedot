<?php

namespace App\Models;

use App\Support\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 매장
class Shop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'industry', 'labels', 'business_hours',
        'slot_unit', 'cancel_policy', 'theme',
    ];

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'business_hours' => 'array',
            'cancel_policy' => 'array',
            'slot_unit' => 'integer',
        ];
    }

    public function staffs(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    // 화면에 적용할 계절 (theme 이 auto 면 오늘 날짜로 결정)
    public function season(): string
    {
        return Season::resolve($this->theme);
    }
}
