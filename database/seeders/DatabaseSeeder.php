<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\Staff;
use Illuminate\Database\Seeder;

// 로그인 확인용 최소 데이터 (업종별 데모 매장 3개는 Phase 2에서)
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::updateOrCreate(
            ['slug' => 'demo-hair'],
            ['name' => '노트닷 헤어', 'industry' => 'beauty', 'slot_unit' => 30],
        );

        Staff::updateOrCreate(
            ['shop_id' => $shop->id, 'email' => 'owner@notedot.test'],
            ['name' => '김원장', 'role' => 'owner', 'password' => 'notedot1234'],
        );
    }
}
