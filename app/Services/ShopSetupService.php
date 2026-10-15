<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;

// 새 매장 만들기: 업종 프리셋 기본값을 채우고 대표 계정까지 한 번에
class ShopSetupService
{
    // 기본 영업시간: 월~토 10~20시, 일요일 휴무
    public const DEFAULT_HOURS = [
        'mon' => ['open' => '10:00', 'close' => '20:00'],
        'tue' => ['open' => '10:00', 'close' => '20:00'],
        'wed' => ['open' => '10:00', 'close' => '20:00'],
        'thu' => ['open' => '10:00', 'close' => '20:00'],
        'fri' => ['open' => '10:00', 'close' => '20:00'],
        'sat' => ['open' => '10:00', 'close' => '20:00'],
        'sun' => null,
    ];

    /**
     * @param  array{slug:string,name:string,industry:string,business_hours?:array}  $shop
     * @param  array{name:string,email:string,password:string}  $owner
     */
    public function create(array $shop, array $owner): Shop
    {
        return DB::transaction(function () use ($shop, $owner) {
            $preset = config("industries.{$shop['industry']}");

            $model = Shop::create([
                'slug' => $shop['slug'],
                'name' => $shop['name'],
                'industry' => $shop['industry'],
                'slot_unit' => $preset['slot_unit'] ?? 30,
                'business_hours' => $shop['business_hours'] ?? self::DEFAULT_HOURS,
            ]);

            $model->staffs()->create($owner + ['role' => 'owner']);

            return $model;
        });
    }
}
