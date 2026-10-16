<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// 공간 대여 (스터디룸): 담당자 없이 공간을 시간 단위로 빌린다
class SpaceBookingTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'demo-studyroom';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->actingAs($this->staff('studyroom@notedot.test'), 'admin');
    }

    // 기본값: A룸 · 2시간 이용 · 14:00
    private function book(array $overrides = []): TestResponse
    {
        return $this->postJson(route('admin.reservations.store'), $overrides + [
            'customer_name' => '이용자',
            'customer_phone' => '010-5555-6666',
            'resource_id' => $this->resourceNamed('A룸 (4인)', self::SLUG)->id,
            'service_ids' => [$this->serviceNamed('2시간 이용', self::SLUG)->id],
            'date' => '2030-01-07',
            'start_time' => '14:00',
            'party_size' => 3,
        ]);
    }

    public function test_room_booking_uses_service_length(): void
    {
        $id = $this->book()->assertCreated()->json('id');

        $r = Reservation::findOrFail($id);
        $this->assertSame('16:00', $r->end_at->format('H:i'));
        $this->assertSame('16:00', $r->occupied_until->format('H:i'));   // 공간 상품은 정리 시간 0
        $this->assertSame(3, $r->party_size);
        $this->assertSame(15000, $r->total_price);
    }

    public function test_same_room_overlap_is_rejected(): void
    {
        $this->book()->assertCreated();

        $this->book(['start_time' => '15:00'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    // 정리 시간이 없으므로 끝나는 시각에 바로 다음 예약 가능
    public function test_back_to_back_booking_is_allowed(): void
    {
        $this->book()->assertCreated();

        $this->book(['start_time' => '16:00'])->assertCreated();
    }

    public function test_other_room_can_take_same_time(): void
    {
        $this->book()->assertCreated();

        $this->book(['resource_id' => $this->resourceNamed('B룸 (6인)', self::SLUG)->id])->assertCreated();
    }

    public function test_several_hour_products_add_up(): void
    {
        $id = $this->book(['service_ids' => [
            $this->serviceNamed('2시간 이용', self::SLUG)->id,
            $this->serviceNamed('1시간 이용', self::SLUG)->id,
        ]])->assertCreated()->json('id');

        $this->assertSame('17:00', Reservation::findOrFail($id)->end_at->format('H:i'));
    }

    // 공간에 1:1 상품, 사람에게 공간 상품을 연결해 두었더라도 예약은 막는다
    public function test_service_type_must_match_resource_type(): void
    {
        $room = $this->resourceNamed('A룸 (4인)', self::SLUG);
        $person = $this->shop(self::SLUG)->services()->create([
            'name' => '1:1 상담', 'booking_type' => 'one_to_one', 'duration_min' => 60, 'price' => 10000,
        ]);
        $room->services()->attach($person->id);

        $this->book(['service_ids' => [$person->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_ids');
    }

    // A룸은 최대 4명: 4명은 되고 5명은 안 됨
    public function test_party_size_is_limited_by_room_size(): void
    {
        $this->book(['party_size' => 5])->assertUnprocessable()->assertJsonValidationErrors('party_size');

        $this->book(['party_size' => 4])->assertCreated();
    }

    public function test_editing_cannot_exceed_room_size(): void
    {
        $id = $this->book()->json('id');

        $this->putJson(route('admin.reservations.update', $id), [
            'customer_name' => '이용자',
            'customer_phone' => '010-5555-6666',
            'resource_id' => $this->resourceNamed('A룸 (4인)', self::SLUG)->id,
            'service_ids' => [$this->serviceNamed('2시간 이용', self::SLUG)->id],
            'date' => '2030-01-07',
            'start_time' => '14:00',
            'party_size' => 8,
        ])->assertUnprocessable()->assertJsonValidationErrors('party_size');

        // 더 큰 방(C룸 10인)으로 옮기면 8명 가능
        $this->putJson(route('admin.reservations.update', $id), [
            'customer_name' => '이용자',
            'customer_phone' => '010-5555-6666',
            'resource_id' => $this->resourceNamed('C룸 (10인)', self::SLUG)->id,
            'service_ids' => [$this->serviceNamed('2시간 이용', self::SLUG)->id],
            'date' => '2030-01-07',
            'start_time' => '14:00',
            'party_size' => 8,
        ])->assertOk();
    }

    // 최대 인원이 비어 있으면 (미용실 담당자 등) 제한 없음
    public function test_empty_max_party_means_no_limit(): void
    {
        $this->actingAs($this->staff(), 'admin')->postJson(route('admin.reservations.store'), [
            'customer_name' => '테스트',
            'customer_phone' => '010-1234-5678',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07',
            'start_time' => '10:00',
            'party_size' => 3,
        ])->assertCreated();
    }

    public function test_room_form_saves_max_party(): void
    {
        $room = $this->resourceNamed('A룸 (4인)', self::SLUG);

        $this->put(route('admin.resources.update', $room), [
            'type' => 'space', 'name' => $room->name, 'color' => $room->color, 'capacity' => 1,
            'max_party' => 5, 'sort_order' => 0, 'is_active' => 1,
            'service_ids' => $room->services()->pluck('services.id')->all(),
        ])->assertRedirect();

        $this->assertSame(5, $room->fresh()->max_party);
    }

    public function test_calendar_config_tells_resource_type(): void
    {
        $this->get(route('admin.calendar'))
            ->assertOk()
            ->assertViewHas('config', fn (array $config) => $config['resources']->pluck('type')->unique()->all() === ['space']);
    }
}
