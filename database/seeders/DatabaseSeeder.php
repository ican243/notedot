<?php

namespace Database\Seeders;

use App\Contracts\RealtimePublisher;
use App\Services\Realtime\PollingPublisher;
use Illuminate\Database\Seeder;

// php artisan db:seed 로 실행되는 시작점
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 데모 데이터 수백 건을 넣는 동안에는 실시간 알림을 보내지 않는다
        app()->instance(RealtimePublisher::class, new PollingPublisher);

        $this->call([
            DemoShopSeeder::class,
            DemoClassSessionSeeder::class,     // 일정을 먼저 → 1:1 예약은 일정 시간을 피해서 들어간다
            DemoReservationSeeder::class,
        ]);
    }
}
