<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// 그룹 일정 (정원제): 일정 만들기·반복, 정원, 1:1 예약과의 겹침, 이동, 취소
class GroupClassTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'demo-pilates';

    private const DATE = '2030-01-07';   // 월요일

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->actingAs($this->staff('pilates@notedot.test'), 'admin');
    }

    // 기본값: 하은 · 그룹 리포머(50분 + 정리 10분, 정원 6) · 10:00
    private function makeSession(array $overrides = []): TestResponse
    {
        return $this->postJson(route('admin.class-sessions.store'), $overrides + [
            'resource_id' => $this->resourceNamed('하은', self::SLUG)->id,
            'service_id' => $this->serviceNamed('그룹 리포머', self::SLUG)->id,
            'date' => self::DATE,
            'start_time' => '10:00',
        ]);
    }

    private function sessionId(array $overrides = []): int
    {
        return $this->makeSession($overrides)->assertCreated()->json('ids.0');
    }

    private function join(int $sessionId, string $phone, array $overrides = []): TestResponse
    {
        return $this->postJson(route('admin.class-sessions.book', $sessionId), $overrides + [
            'customer_name' => '회원'.substr($phone, -4),
            'customer_phone' => $phone,
        ]);
    }

    // 1:1 예약 (하은 · 1:1 개인 레슨 50분 + 10분)
    private function personal(string $time): TestResponse
    {
        return $this->postJson(route('admin.reservations.store'), [
            'customer_name' => '개인',
            'customer_phone' => '010-7777-0000',
            'resource_id' => $this->resourceNamed('하은', self::SLUG)->id,
            'service_ids' => [$this->serviceNamed('1:1 개인 레슨', self::SLUG)->id],
            'date' => self::DATE,
            'start_time' => $time,
        ]);
    }

    public function test_session_copies_service_capacity_and_times(): void
    {
        $s = ClassSession::findOrFail($this->sessionId());

        $this->assertSame(6, $s->capacity);
        $this->assertSame('10:50', $s->end_at->format('H:i'));
        $this->assertSame('11:00', $s->occupied_until->format('H:i'));
        $this->assertTrue(AuditLog::where('target_type', 'class_sessions')->where('target_id', $s->id)->exists());
    }

    // 월요일부터 2주 동안 월·수·금 → 6개
    public function test_repeat_creates_sessions_on_chosen_days(): void
    {
        $ids = $this->makeSession(['repeat_days' => [1, 3, 5], 'repeat_weeks' => 2, 'capacity' => 4])
            ->assertCreated()->json('ids');

        $sessions = ClassSession::whereIn('id', $ids)->orderBy('start_at')->get();
        $this->assertCount(6, $sessions);
        $this->assertSame(['2030-01-07', '2030-01-09', '2030-01-11', '2030-01-14', '2030-01-16', '2030-01-18'],
            $sessions->map(fn ($s) => $s->start_at->format('Y-m-d'))->all());
        $this->assertSame([4], $sessions->pluck('capacity')->unique()->values()->all());
    }

    // 반복 중 하나라도 겹치면 하나도 만들지 않고, 겹치는 날짜를 알려 준다
    public function test_repeat_is_all_or_nothing(): void
    {
        $this->postJson(route('admin.reservations.store'), [
            'customer_name' => '개인', 'customer_phone' => '010-7777-0000',
            'resource_id' => $this->resourceNamed('하은', self::SLUG)->id,
            'service_ids' => [$this->serviceNamed('1:1 개인 레슨', self::SLUG)->id],
            'date' => '2030-01-09', 'start_time' => '10:30',
        ])->assertCreated();

        $res = $this->makeSession(['repeat_days' => [1, 3, 5], 'repeat_weeks' => 1])->assertUnprocessable();

        $this->assertStringStartsWith('1/9(수)', $res->json('errors.start_time.0'));
        $this->assertSame(0, ClassSession::count());
    }

    public function test_only_group_service_can_make_session(): void
    {
        $this->makeSession(['service_id' => $this->serviceNamed('1:1 개인 레슨', self::SLUG)->id])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_seats_fill_up_then_reject(): void
    {
        $id = $this->sessionId(['capacity' => 3]);

        $this->join($id, '010-1000-0001')->assertCreated();
        $this->join($id, '010-1000-0002', ['party_size' => 2])->assertCreated();

        $this->join($id, '010-1000-0003')->assertUnprocessable()->assertJsonValidationErrors('party_size');
        $this->assertSame(3, (int) Reservation::where('class_session_id', $id)->sum('party_size'));
    }

    public function test_party_bigger_than_seats_left_is_rejected(): void
    {
        $id = $this->sessionId(['capacity' => 3]);
        $this->join($id, '010-1000-0001', ['party_size' => 2])->assertCreated();

        $res = $this->join($id, '010-1000-0002', ['party_size' => 2])->assertUnprocessable();
        $this->assertStringContainsString('1명', $res->json('errors.party_size.0'));
    }

    public function test_same_customer_cannot_join_twice(): void
    {
        $id = $this->sessionId();
        $this->join($id, '010-1000-0001')->assertCreated();

        $this->join($id, '010-1000-0001')->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }

    public function test_group_price_is_per_person(): void
    {
        $id = $this->sessionId();
        $rid = $this->join($id, '010-1000-0001', ['party_size' => 2])->json('id');

        $this->assertSame(50000, Reservation::findOrFail($rid)->total_price);   // 25,000원 × 2명
    }

    // 취소하면 자리가 비고, 그 자리가 다시 차면 취소를 되돌릴 수 없다
    public function test_cancel_frees_seat_and_restore_checks_seats(): void
    {
        $id = $this->sessionId(['capacity' => 1]);
        $first = $this->join($id, '010-1000-0001')->json('id');

        $this->patchJson(route('admin.reservations.status', $first), ['status' => 'canceled'])->assertOk();
        $this->join($id, '010-1000-0002')->assertCreated();

        $this->patchJson(route('admin.reservations.status', $first), ['status' => 'confirmed'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_personal_booking_cannot_overlap_session(): void
    {
        $this->sessionId();

        $this->personal('10:30')->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->personal('11:00')->assertCreated();   // 정리 시간(~11:00) 이 끝난 뒤는 가능
    }

    public function test_session_cannot_overlap_personal_booking(): void
    {
        $this->personal('09:30')->assertCreated();   // 09:30~10:20, 정리 ~10:30

        $this->makeSession()->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->makeSession(['start_time' => '10:30'])->assertCreated();
    }

    public function test_capacity_cannot_go_below_taken_seats(): void
    {
        $id = $this->sessionId();
        $this->join($id, '010-1000-0001', ['party_size' => 3])->assertCreated();

        $this->putJson(route('admin.class-sessions.update', $id), ['capacity' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $this->putJson(route('admin.class-sessions.update', $id), ['capacity' => 3, 'memo' => '소도구'])->assertOk();

        $this->assertSame(3, ClassSession::findOrFail($id)->capacity);
    }

    // 일정을 옮기면 신청도 같이 옮겨지고, 겹치는 곳으로는 못 옮긴다
    public function test_move_carries_reservations_and_checks_conflicts(): void
    {
        $id = $this->sessionId();
        $rid = $this->join($id, '010-1000-0001')->json('id');
        $this->personal('14:00')->assertCreated();

        $this->patchJson(route('admin.class-sessions.move', $id), ['start' => self::DATE.'T14:30:00+09:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('start');

        $this->patchJson(route('admin.class-sessions.move', $id), ['start' => self::DATE.'T16:00:00+09:00'])->assertOk();

        $this->assertSame('16:00', ClassSession::findOrFail($id)->start_at->format('H:i'));
        $r = Reservation::findOrFail($rid);
        $this->assertSame('16:00', $r->start_at->format('H:i'));
        $this->assertSame('17:00', $r->occupied_until->format('H:i'));
    }

    public function test_group_reservation_is_not_moved_alone(): void
    {
        $rid = $this->join($this->sessionId(), '010-1000-0001')->json('id');

        $this->patchJson(route('admin.reservations.move', $rid), ['start' => self::DATE.'T15:00:00+09:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    // 일정 취소: 대기·확정 신청은 취소, 완료·노쇼는 그대로, 새 신청 불가, 그 시간에 1:1 예약 가능
    public function test_cancel_session_cancels_active_reservations(): void
    {
        $id = $this->sessionId();
        $a = $this->join($id, '010-1000-0001')->json('id');
        $b = $this->join($id, '010-1000-0002')->json('id');
        $this->patchJson(route('admin.reservations.status', $b), ['status' => 'no_show'])->assertOk();

        $this->patchJson(route('admin.class-sessions.cancel', $id), ['reason' => '강사 사정'])->assertOk();

        $this->assertSame('canceled', Reservation::findOrFail($a)->status);
        $this->assertSame('no_show', Reservation::findOrFail($b)->status);
        $this->join($id, '010-1000-0003')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->personal('10:00')->assertCreated();
    }

    // 캘린더에는 일정 블록 하나 (신청자 블록을 따로 그리지 않음)
    public function test_calendar_shows_one_block_per_session(): void
    {
        $id = $this->sessionId();
        $this->join($id, '010-1000-0001', ['party_size' => 2]);
        $this->join($id, '010-1000-0002');

        $events = $this->getJson(route('admin.calendar.events', [
            'start' => '2030-01-06T00:00:00+09:00',
            'end' => '2030-01-13T00:00:00+09:00',
            'resources' => $this->shop(self::SLUG)->resources()->pluck('id')->all(),
        ]))->assertOk()->json();

        $this->assertCount(1, $events);
        $this->assertSame('s'.$id, $events[0]['id']);
        $this->assertSame(3, $events[0]['extendedProps']['taken']);
        $this->assertSame(6, $events[0]['extendedProps']['capacity']);
    }

    public function test_detail_lists_attendees_with_masked_phone(): void
    {
        $id = $this->sessionId();
        $this->join($id, '010-1000-0001');

        $this->getJson(route('admin.class-sessions.show', $id))
            ->assertOk()
            ->assertJsonPath('taken', 1)
            ->assertJsonPath('attendees.0.phone', '010-****-0001');
    }

    public function test_other_shop_cannot_see_session(): void
    {
        $id = $this->sessionId();

        $this->actingAs($this->staff(), 'admin')
            ->getJson(route('admin.class-sessions.show', $id))
            ->assertNotFound();
    }

    public function test_service_layer_counts_only_active_seats(): void
    {
        $id = $this->sessionId();
        $rid = $this->join($id, '010-1000-0001', ['party_size' => 2])->json('id');
        $this->patchJson(route('admin.reservations.status', $rid), ['status' => 'canceled']);

        $this->assertSame(0, app(ReservationService::class)->seatsTaken(ClassSession::findOrFail($id)));
    }
}
