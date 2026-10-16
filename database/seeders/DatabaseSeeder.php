<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// php artisan db:seed 로 실행되는 시작점
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoShopSeeder::class,
            DemoClassSessionSeeder::class,     // 일정을 먼저 → 1:1 예약은 일정 시간을 피해서 들어간다
            DemoReservationSeeder::class,
        ]);
    }
}
