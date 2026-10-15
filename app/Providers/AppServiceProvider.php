<?php

namespace App\Providers;

use App\Support\CurrentShop;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bindWithinShop();
    }

    // 주소의 {resource} {service} {staff} {schedule} {reservation} 는 지금 매장 안에서만 찾는다
    // → 다른 매장 id 를 주소에 넣으면 404 (shop_id 범위 규칙)
    private function bindWithinShop(): void
    {
        $relations = [
            'resource' => 'resources',
            'service' => 'services',
            'staff' => 'staffs',
            'schedule' => 'resourceSchedules',
            'reservation' => 'reservations',
        ];

        foreach ($relations as $param => $relation) {
            Route::bind($param, function (string $value) use ($relation) {
                $shop = CurrentShop::get();
                abort_unless($shop, 404);

                return $shop->{$relation}()->findOrFail($value);
            });
        }
    }
}
