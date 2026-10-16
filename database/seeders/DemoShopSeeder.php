<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Services\CatalogService;
use App\Services\ShopSetupService;
use Illuminate\Database\Seeder;

// 업종별 데모 매장 3개 (미용실 / 필라테스 / 스터디룸). 여러 번 실행해도 같은 상태로 다시 만든다
class DemoShopSeeder extends Seeder
{
    public const PASSWORD = 'notedot1234';

    public function __construct(
        private ShopSetupService $setup,
        private CatalogService $catalog,
    ) {}

    public function run(): void
    {
        // 기존 데모 매장 완전 삭제 → 연결된 표는 외래키 cascade 로 함께 지워진다
        Shop::withTrashed()->whereIn('slug', ['demo-hair', 'demo-pilates', 'demo-studyroom'])->get()
            ->each(fn (Shop $s) => $s->forceDelete());

        $this->hair();
        $this->pilates();
        $this->studyroom();
    }

    private function hair(): void
    {
        $shop = $this->setup->create(
            ['slug' => 'demo-hair', 'name' => '노트닷 헤어', 'industry' => 'beauty'],
            ['name' => '김지수', 'email' => 'owner@notedot.test', 'password' => self::PASSWORD],
        );

        $services = $this->services($shop, [
            ['category' => '커트', 'name' => '남성 커트', 'duration_min' => 30, 'buffer_min' => 10, 'price' => 22000],
            ['category' => '커트', 'name' => '여성 커트', 'duration_min' => 60, 'buffer_min' => 10, 'price' => 30000],
            ['category' => '펌', 'name' => '디지털 펌', 'duration_min' => 150, 'buffer_min' => 10, 'price' => 120000, 'deposit' => 20000],
            ['category' => '펌', 'name' => '다운펌', 'duration_min' => 60, 'buffer_min' => 10, 'price' => 40000],
            ['category' => '컬러', 'name' => '전체 염색', 'duration_min' => 120, 'buffer_min' => 10, 'price' => 90000, 'deposit' => 10000],
        ]);

        $jisu = $this->resource($shop, '지수', '#2463b8', array_values($services));
        $minho = $this->resource($shop, '민호', '#0f8a6a', [$services['남성 커트'], $services['다운펌'], $services['여성 커트']]);
        $this->resource($shop, '서연', '#9b4dca', [$services['여성 커트'], $services['전체 염색'], $services['디지털 펌']]);

        // 대표 계정은 지수와 연결, 직원 계정 1개 추가
        $shop->staffs()->where('role', 'owner')->update(['resource_id' => $jisu->id]);
        $shop->staffs()->create([
            'name' => '이민호', 'email' => 'staff@notedot.test', 'password' => self::PASSWORD,
            'role' => 'staff', 'resource_id' => $minho->id,
        ]);
    }

    private function pilates(): void
    {
        $shop = $this->setup->create(
            [
                'slug' => 'demo-pilates', 'name' => '노트닷 필라테스', 'industry' => 'fitness',
                'business_hours' => $this->hours('07:00', '22:00', sat: ['09:00', '15:00']),
            ],
            ['name' => '박하은', 'email' => 'pilates@notedot.test', 'password' => self::PASSWORD],
        );

        $services = $this->services($shop, [
            ['category' => '개인', 'name' => '1:1 개인 레슨', 'duration_min' => 50, 'buffer_min' => 10, 'price' => 70000],
            ['category' => '개인', 'name' => '2:1 듀엣 레슨', 'duration_min' => 50, 'buffer_min' => 10, 'price' => 50000, 'capacity' => 2],
            ['category' => '그룹', 'name' => '그룹 리포머', 'booking_type' => 'group', 'duration_min' => 50, 'buffer_min' => 10, 'capacity' => 6, 'price' => 25000],
            ['category' => '그룹', 'name' => '그룹 매트', 'booking_type' => 'group', 'duration_min' => 50, 'buffer_min' => 10, 'capacity' => 8, 'price' => 18000],
        ]);

        $haeun = $this->resource($shop, '하은', '#c8557a', array_values($services));
        $this->resource($shop, '도윤', '#1b6fb8', array_values($services));

        $shop->staffs()->where('role', 'owner')->update(['resource_id' => $haeun->id]);
    }

    private function studyroom(): void
    {
        $shop = $this->setup->create(
            [
                'slug' => 'demo-studyroom', 'name' => '노트닷 스터디룸', 'industry' => 'space',
                'business_hours' => $this->hours('09:00', '23:00', sat: ['09:00', '23:00'], sun: ['10:00', '22:00']),
            ],
            ['name' => '최서준', 'email' => 'studyroom@notedot.test', 'password' => self::PASSWORD],
        );

        $services = $this->services($shop, [
            ['name' => '1시간 이용', 'booking_type' => 'space', 'duration_min' => 60, 'price' => 8000],
            ['name' => '2시간 이용', 'booking_type' => 'space', 'duration_min' => 120, 'price' => 15000],
            ['name' => '4시간 이용', 'booking_type' => 'space', 'duration_min' => 240, 'price' => 28000, 'deposit' => 5000],
        ]);

        $all = array_values($services);
        $this->resource($shop, 'A룸 (4인)', '#25a9c0', $all, type: 'space', maxParty: 4);
        $this->resource($shop, 'B룸 (6인)', '#45a05d', $all, type: 'space', maxParty: 6);
        $this->resource($shop, 'C룸 (10인)', '#b45309', $all, type: 'space', maxParty: 10);
    }

    // 상품 여러 개 만들고 이름 => id 로 돌려준다
    private function services(Shop $shop, array $rows): array
    {
        $ids = [];
        foreach ($rows as $i => $row) {
            $service = $this->catalog->saveService($shop, $row + [
                'booking_type' => 'one_to_one', 'buffer_min' => 0, 'capacity' => 1,
                'deposit' => 0, 'sort_order' => $i, 'is_active' => true,
            ]);
            $ids[$service->name] = $service->id;
        }

        return $ids;
    }

    private function resource(Shop $shop, string $name, string $color, array $serviceIds, string $type = 'person', ?int $maxParty = null)
    {
        return $this->catalog->saveResource($shop, [
            'type' => $type, 'name' => $name, 'color' => $color, 'capacity' => 1, 'max_party' => $maxParty,
            'sort_order' => $shop->resources()->count(), 'is_active' => true,
            'service_ids' => $serviceIds,
        ]);
    }

    // 평일 같은 시간 + 토/일 따로 (null 이면 휴무)
    private function hours(string $open, string $close, ?array $sat = null, ?array $sun = null): array
    {
        $h = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri'] as $d) {
            $h[$d] = ['open' => $open, 'close' => $close];
        }
        $h['sat'] = $sat ? ['open' => $sat[0], 'close' => $sat[1]] : null;
        $h['sun'] = $sun ? ['open' => $sun[0], 'close' => $sun[1]] : null;

        return $h;
    }
}
