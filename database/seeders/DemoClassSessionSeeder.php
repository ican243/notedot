<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Services\ClassSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

// 필라테스 데모 매장의 그룹 일정 (지난 5일 ~ 앞으로 2주). 신청자는 DemoReservationSeeder 가 고객을 만든 뒤 채운다
class DemoClassSessionSeeder extends Seeder
{
    // [담당자, 상품, 요일(0=일~6=토), 시작]
    private const TIMETABLE = [
        ['하은', '그룹 리포머', [1, 3, 5], '10:00'],
        ['하은', '그룹 매트', [2, 4], '12:00'],
        ['도윤', '그룹 매트', [2, 4], '19:00'],
        ['도윤', '그룹 리포머', [6], '10:00'],
    ];

    public function __construct(private ClassSessionService $sessions) {}

    public function run(): void
    {
        $shop = Shop::where('slug', 'demo-pilates')->first();
        if (! $shop) {
            return;
        }
        $owner = $shop->staffs()->where('role', 'owner')->first();
        $from = CarbonImmutable::today()->subDays(5);

        foreach (self::TIMETABLE as [$resource, $service, $days, $time]) {
            $this->sessions->create($shop, $owner, [
                'resource_id' => $shop->resources()->where('name', $resource)->value('id'),
                'service_id' => $shop->services()->where('name', $service)->value('id'),
                'date' => $from->format('Y-m-d'),
                'start_time' => $time,
                'repeat_days' => $days,
                'repeat_weeks' => 3,
            ]);
        }
    }
}
