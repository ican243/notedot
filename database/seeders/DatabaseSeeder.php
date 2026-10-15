<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// php artisan db:seed 로 실행되는 시작점
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoShopSeeder::class);
    }
}
