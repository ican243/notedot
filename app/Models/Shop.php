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

    // 업종 프리셋에도 없을 때 쓰는 공통 용어
    public const FALLBACK_LABELS = ['resource' => '담당', 'service' => '상품', 'customer' => '고객'];

    // business_hours JSON 의 요일 키 (월요일부터 화면 순서)
    public const DAY_KEYS = ['mon' => '월', 'tue' => '화', 'wed' => '수', 'thu' => '목', 'fri' => '금', 'sat' => '토', 'sun' => '일'];

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

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class)->orderBy('sort_order')->orderBy('id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function resourceSchedules(): HasMany
    {
        return $this->hasMany(ResourceSchedule::class);
    }

    // 업종 프리셋 전체 (config/industries.php)
    public function preset(): array
    {
        return config("industries.{$this->industry}", []);
    }

    // 화면 용어: 매장 labels → 업종 프리셋 → 공통 기본값
    public function term(string $key): string
    {
        return $this->labels[$key]
            ?? $this->preset()['labels'][$key]
            ?? self::FALLBACK_LABELS[$key]
            ?? $key;
    }

    // 이 매장에서 쓸 수 있는 예약 방식
    public function bookingTypes(): array
    {
        return $this->preset()['booking_types'] ?? ['one_to_one'];
    }

    // 화면에 적용할 계절 (theme 이 auto 면 오늘 날짜로 결정)
    public function season(): string
    {
        return Season::resolve($this->theme);
    }
}
