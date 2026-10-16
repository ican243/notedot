<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Shop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

// 데모 매장 3개와 예약을 처음 상태로 되돌린다 (매일 새벽 스케줄러가 실행)
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : 데모 모드가 꺼져 있어도 실행}';

    protected $description = '데모 매장·예약 데이터를 처음 상태로 되돌립니다';

    public function handle(): int
    {
        if (! config('demo.enabled') && ! $this->option('force')) {
            $this->error('데모 모드(DEMO_MODE)가 꺼져 있습니다. 그래도 실행하려면 --force 를 붙이세요.');

            return self::FAILURE;
        }

        // 데모 시더는 데모 매장(slug)만 지우고 다시 만든다 → 다른 매장 데이터는 그대로
        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->info(sprintf('데모 데이터 초기화 완료 (매장 %d곳, 예약 %d건)', Shop::count(), Reservation::count()));

        return self::SUCCESS;
    }
}
