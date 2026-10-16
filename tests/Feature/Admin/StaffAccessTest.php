<?php

namespace Tests\Feature\Admin;

use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 직원 계정은 자기 resource 예약만, 대표 전용 화면은 못 들어감
class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
    }

    private function reservationFor(string $resource, string $time): int
    {
        return app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => '테스트',
            'customer_phone' => '010-1234-5678',
            'resource_id' => $this->resourceNamed($resource)->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07',
            'start_time' => $time,
        ])->id;
    }

    public function test_staff_calendar_shows_only_own_resource(): void
    {
        $this->reservationFor('지수', '10:00');
        $mine = $this->reservationFor('민호', '11:00');

        $events = $this->actingAs($this->staff('staff@notedot.test'), 'admin')
            ->getJson(route('admin.calendar.events', [
                'start' => '2030-01-06T00:00:00+09:00',
                'end' => '2030-01-13T00:00:00+09:00',
                'resources' => $this->shop()->resources()->pluck('id')->all(),
            ]))
            ->assertOk()
            ->json();

        $this->assertSame([$mine], array_column($events, 'id'));
    }

    public function test_staff_cannot_open_other_resource_reservation(): void
    {
        $id = $this->reservationFor('지수', '10:00');

        $this->actingAs($this->staff('staff@notedot.test'), 'admin')
            ->getJson(route('admin.reservations.show', $id))
            ->assertForbidden();
    }

    public function test_staff_cannot_book_for_other_resource(): void
    {
        $this->actingAs($this->staff('staff@notedot.test'), 'admin')
            ->postJson(route('admin.reservations.store'), [
                'customer_name' => '테스트',
                'customer_phone' => '010-1234-5678',
                'resource_id' => $this->resourceNamed('지수')->id,
                'service_ids' => [$this->serviceNamed('남성 커트')->id],
                'date' => '2030-01-07',
                'start_time' => '10:00',
            ])
            ->assertForbidden();
    }

    public function test_staff_cannot_open_owner_pages(): void
    {
        $this->actingAs($this->staff('staff@notedot.test'), 'admin');

        $this->get(route('admin.settings.shop'))->assertForbidden();
        $this->get(route('admin.staffs.index'))->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.calendar'))->assertRedirect(route('admin.login'));
    }
}
