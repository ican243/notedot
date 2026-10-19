<?php

namespace Tests\Feature\Admin;

use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 캘린더 블록 정보: 색은 담당(resourceId·color), 상태는 글자(statusLabel)·모양(classNames). 원색으로 꽉 칠하지 않는다
class CalendarViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
    }

    private function events(array $query = []): array
    {
        return $this->actingAs($this->staff(), 'admin')
            ->getJson(route('admin.calendar.events', $query + [
                'start' => '2030-01-07',
                'end' => '2030-01-08',
                'resources' => $this->shop()->resources()->pluck('id')->all(),
            ]))
            ->assertOk()
            ->json();
    }

    public function test_reservation_block_carries_resource_color_and_status_text(): void
    {
        $resource = $this->resourceNamed('민호');
        $r = app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => '김하늘', 'customer_phone' => '010-2222-3333',
            'resource_id' => $resource->id, 'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07', 'start_time' => '10:00',
        ]);
        app(ReservationService::class)->changeStatus($r, $this->staff(), 'no_show');

        $e = collect($this->events())->firstWhere('id', $r->id);

        $this->assertSame('김하늘', $e['title']);
        $this->assertSame(['ev', 'ev-no_show'], $e['classNames']);
        $this->assertSame($resource->id, $e['extendedProps']['resourceId']);
        $this->assertSame($resource->color, $e['extendedProps']['color']);
        $this->assertSame('노쇼', $e['extendedProps']['statusLabel']);
        $this->assertSame('남성 커트', $e['extendedProps']['services']);
        $this->assertArrayNotHasKey('backgroundColor', $e);   // 진한 바탕은 화면이 담당 색을 연하게 칠한다
    }

    public function test_date_only_range_is_one_whole_day(): void
    {
        $svc = [$this->serviceNamed('남성 커트')->id];
        foreach (['2030-01-07' => '10:00', '2030-01-08' => '10:00'] as $date => $time) {
            app(ReservationService::class)->create($this->shop(), null, [
                'customer_name' => '이바다', 'customer_phone' => '010-4444-5555',
                'resource_id' => $this->resourceNamed('지수')->id, 'service_ids' => $svc, 'date' => $date, 'start_time' => $time,
            ]);
        }

        // 일간(담당자 칸) 보기는 날짜만 보낸다 → 그날 예약만
        $this->assertCount(1, $this->events());
    }

    public function test_calendar_page_has_day_week_toggle_and_status_legend(): void
    {
        $this->actingAs($this->staff(), 'admin')->get(route('admin.calendar'))->assertOk()
            ->assertSee("setView('day')", false)
            ->assertSee("setView('week')", false)
            ->assertSee('ev-badge-pending', false)
            ->assertSee('배지 없음 = 확정');
    }
}
