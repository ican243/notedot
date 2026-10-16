<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Shop;
use App\Services\PassService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

// 데모 회원권: 필라테스는 횟수권(지난 수업에서 차감), 미용실·스터디룸은 정액권 몇 장
class DemoPassSeeder extends Seeder
{
    public function __construct(private PassService $passes) {}

    public function run(): void
    {
        $today = CarbonImmutable::today();

        $this->issueAndUse('demo-pilates', 10, fn (int $i) => [
            'type' => 'count',
            'name' => $i % 3 === 0 ? '20회권' : '10회권',
            'price' => $i % 3 === 0 ? 900000 : 500000,
            'total' => $i % 3 === 0 ? 20 : 10,
            'expires_on' => $today->addDays(60 + $i * 7)->toDateString(),
        ]);

        $this->issueAndUse('demo-hair', 4, fn (int $i) => [
            'type' => 'amount',
            'name' => $i % 2 ? '30만원 정액권' : '50만원 정액권',
            'price' => $i % 2 ? 300000 : 500000,
            'total' => $i % 2 ? 330000 : 550000,   // 10% 더 얹어 주는 충전권
            'expires_on' => null,
        ]);

        $this->issueAndUse('demo-studyroom', 3, fn (int $i) => [
            'type' => 'amount',
            'name' => '10만원 충전권',
            'price' => 100000,
            'total' => 110000,
            'expires_on' => $today->addYear()->toDateString(),
        ]);
    }

    // 앞에서부터 고객 $count 명에게 발급하고, 그 고객의 지난 완료 예약을 그 권으로 처리
    private function issueAndUse(string $slug, int $count, callable $data): void
    {
        $shop = Shop::where('slug', $slug)->firstOrFail();
        $owner = $shop->staffs()->where('role', 'owner')->first();

        $shop->customers()->orderBy('id')->limit($count)->get()->each(function (Customer $customer, int $i) use ($owner, $data) {
            $pass = $this->passes->issue($customer, $owner, $data($i) + ['memo' => '데모']);

            $customer->reservations()->where('status', 'completed')->orderBy('start_at')->limit(3)->get()
                ->each(function (Reservation $r) use ($pass, $owner) {
                    if ($pass->refresh()->balance >= $this->passes->needFor($pass, $r)) {
                        $this->passes->useFor($r, $pass, $owner);
                    }
                });
        });
    }
}
