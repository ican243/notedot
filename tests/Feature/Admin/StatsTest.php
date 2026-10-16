<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Services\PassService;
use App\Services\ReservationService;
use App\Services\StatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 통계: 손으로 계산한 값과 같은지 (매출·객단가·노쇼율·재방문율·상품 비중·추이·경로·가동률), 기간 고르기, 권한
//
// 미용실 지수(월~토 10:00~20:00), 2030년 1월
//   A  1/7 남성 커트 22,000 완료, 1/8 여성 커트 30,000 완료   → 기간 안 2번 = 재방문
//   B  1/8 남성 커트 완료 (12/20 에도 완료)                    → 기간 전 방문 = 재방문
//   C  1/9 남성 커트 노쇼
//   D  1/9 남성 커트 취소
//   E  1/10 남성 커트 완료                                     → 신규
class StatsTest extends TestCase
{
    use RefreshDatabase;

    private StatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->stats = app(StatsService::class);

        $this->travelTo(CarbonImmutable::parse('2029-12-01 08:00'));
        $this->book('B', '2029-12-20', '10:00', 'completed');

        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));
        $a = $this->book('A', '2030-01-07', '10:00', 'completed');
        $this->book('A', '2030-01-08', '10:00', 'completed', '여성 커트');
        $this->book('B', '2030-01-08', '12:00', 'completed');
        $this->book('C', '2030-01-09', '10:00', 'no_show');
        $this->book('D', '2030-01-09', '12:00', 'canceled');
        $this->book('E', '2030-01-10', '10:00', 'completed');
        app(PassService::class)->issue($a->customer, null, ['type' => 'count', 'name' => '10회권', 'price' => 100000, 'total' => 10]);

        $this->travelTo(CarbonImmutable::parse('2030-01-11 08:00'));
    }

    private function book(string $who, string $date, string $time, string $status, string $service = '남성 커트'): Reservation
    {
        $phones = ['A' => '010-1000-0001', 'B' => '010-1000-0002', 'C' => '010-1000-0003', 'D' => '010-1000-0004', 'E' => '010-1000-0005'];
        $r = app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => $who, 'customer_phone' => $phones[$who],
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed($service)->id],
            'date' => $date, 'start_time' => $time,
        ]);
        if ($status !== 'confirmed') {
            app(ReservationService::class)->changeStatus($r, null, $status);
        }

        return $r;
    }

    private function january(): array
    {
        return $this->stats->summary($this->shop(), CarbonImmutable::parse('2030-01-01'), CarbonImmutable::parse('2030-01-31'));
    }

    public function test_kpis(): void
    {
        $k = $this->january()['kpi'];

        $this->assertSame(96000, $k['revenue']);          // 22,000 + 30,000 + 22,000 + 22,000
        $this->assertSame(22000, $k['revenue_prev']);     // 12월 B
        $this->assertSame(4, $k['visits']);
        $this->assertSame(24000, $k['avg_ticket']);
        $this->assertSame(20.0, $k['no_show_rate']);      // 노쇼 1 / (완료 4 + 노쇼 1)
        $this->assertSame(100000, $k['pass_sales']);
    }

    public function test_revisit_rate(): void
    {
        $this->assertSame(['total' => 3, 'returning' => 2, 'new' => 1, 'revisit_rate' => 66.7], $this->january()['customers']);
    }

    // 상품 비중의 합 = 매출 (같은 규칙으로 계산)
    public function test_services_share_adds_up_to_revenue(): void
    {
        $services = $this->january()['services'];

        $this->assertSame([
            ['name' => '남성 커트', 'count' => 3, 'revenue' => 66000, 'share' => 68.8],
            ['name' => '여성 커트', 'count' => 1, 'revenue' => 30000, 'share' => 31.3],
        ], $services);
        $this->assertSame(96000, array_sum(array_column($services, 'revenue')));
    }

    public function test_daily_trend_fills_empty_days(): void
    {
        $trend = collect($this->january()['trend'])->keyBy('key');

        $this->assertCount(31, $trend);
        $this->assertSame(22000, $trend['2030-01-07']['value']);
        $this->assertSame(52000, $trend['2030-01-08']['value']);
        $this->assertSame(0, $trend['2030-01-09']['value']);   // 노쇼·취소는 매출 아님
        $this->assertSame('1/8', $trend['2030-01-08']['label']);
    }

    public function test_long_period_groups_by_month(): void
    {
        $trend = $this->stats->summary($this->shop(), CarbonImmutable::parse('2029-12-01'), CarbonImmutable::parse('2030-03-31'))['trend'];

        $this->assertSame(['2029-12', '2030-01', '2030-02', '2030-03'], array_column($trend, 'key'));
        $this->assertSame([22000, 96000, 0, 0], array_column($trend, 'value'));
    }

    public function test_channels_exclude_canceled(): void
    {
        $this->assertEquals(['online' => 0, 'phone' => 5, 'walk_in' => 0], $this->january()['channels']);
    }

    // 가동률: 1/1 ~ 오늘(1/11)까지, 일요일(1/6) 빼고 10일 × 600분 = 100시간. 예약 30+60+30+30+30 = 180분(취소 제외) = 3시간
    public function test_resource_utilization_until_today(): void
    {
        $jisu = collect($this->january()['resources'])->firstWhere('name', '지수');

        $this->assertSame(100.0, $jisu['open_hours']);
        $this->assertSame(3.0, $jisu['busy_hours']);
        $this->assertSame(3, $jisu['utilization']);
        $this->assertSame(4, $jisu['count']);
        $this->assertSame(96000, $jisu['revenue']);
    }

    public function test_period_presets(): void
    {
        [$a, $b, $p] = $this->stats->period('last_month');
        $this->assertSame(['2029-12-01', '2029-12-31', 'last_month'], [$a->toDateString(), $b->toDateString(), $p]);

        [$a, $b] = $this->stats->period('last_30');
        $this->assertSame(['2029-12-13', '2030-01-11'], [$a->toDateString(), $b->toDateString()]);

        // 잘못된 값 → 이번 달
        foreach ([['custom', '2030-02-01', '2030-01-01'], ['custom', 'nope', null], ['custom', '2020-01-01', '2030-01-01'], ['hack', null, null]] as [$preset, $from, $to]) {
            [$a, $b, $p] = $this->stats->period($preset, $from, $to);
            $this->assertSame(['2030-01-01', '2030-01-31', 'this_month'], [$a->toDateString(), $b->toDateString(), $p]);
        }
    }

    public function test_other_shop_data_is_not_counted(): void
    {
        $pilates = $this->stats->summary($this->shop('demo-pilates'), CarbonImmutable::parse('2030-01-01'), CarbonImmutable::parse('2030-01-31'));

        $this->assertSame(0, $pilates['kpi']['revenue']);
        $this->assertSame([], $pilates['services']);
    }

    // ── 화면 ──

    public function test_owner_sees_stats_page(): void
    {
        $this->actingAs($this->staff(), 'admin')
            ->get(route('admin.stats', ['period' => 'custom', 'from' => '2030-01-01', 'to' => '2030-01-31']))
            ->assertOk()
            ->assertSee('96,000')
            ->assertSee('66.7%')
            ->assertViewHas('preset', 'custom');
    }

    public function test_staff_cannot_see_stats_or_revenue(): void
    {
        $this->actingAs($this->staff('staff@notedot.test'), 'admin');

        $this->get(route('admin.stats'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('이번 달 매출')->assertDontSee('통계');
    }

    public function test_dashboard_counts_today_for_owner(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-08 09:00'));

        $this->actingAs($this->staff(), 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('cards', fn ($cards) => $cards[0] === ['오늘 예약', '2건'] && $cards[2][0] === '이번 달 매출')
            ->assertSee('여성 커트');
    }
}
