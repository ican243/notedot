<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// 관리자 예약 API: 만들기·겹침 막기·이동·상태 변경
class ReservationBookingTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2030-01-07';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->actingAs($this->staff(), 'admin');
    }

    // 기본값: 지수 · 남성 커트(30분 + 정리 10분) · 10:00
    private function book(array $overrides = []): TestResponse
    {
        return $this->postJson(route('admin.reservations.store'), $overrides + [
            'customer_name' => '테스트',
            'customer_phone' => '010-1234-5678',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => self::DATE,
            'start_time' => '10:00',
        ]);
    }

    public function test_booking_saves_times_snapshot_and_log(): void
    {
        $id = $this->book()->assertCreated()->json('id');

        $r = Reservation::with('items')->findOrFail($id);
        $this->assertSame(self::DATE.' 10:30', $r->end_at->format('Y-m-d H:i'));
        $this->assertSame(self::DATE.' 10:40', $r->occupied_until->format('Y-m-d H:i'));
        $this->assertSame(22000, $r->total_price);
        $this->assertSame('남성 커트', $r->items->first()->service_name);
        $this->assertTrue(AuditLog::where('target_id', $id)->where('action', 'created')->exists());
    }

    // 10:00 예약의 정리 시간(10:30~10:40)에 걸리면 거절, 10:40 부터는 가능
    public function test_buffer_time_blocks_next_booking(): void
    {
        $this->book()->assertCreated();

        $this->book(['start_time' => '10:30', 'customer_phone' => '010-2222-3333'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->book(['start_time' => '10:40', 'customer_phone' => '010-2222-3333'])->assertCreated();
    }

    public function test_other_resource_can_take_same_time(): void
    {
        $this->book()->assertCreated();

        $this->book(['resource_id' => $this->resourceNamed('민호')->id])->assertCreated();
    }

    public function test_service_not_offered_by_resource_is_rejected(): void
    {
        $this->book([
            'resource_id' => $this->resourceNamed('민호')->id,
            'service_ids' => [$this->serviceNamed('디지털 펌')->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('service_ids');
    }

    public function test_other_shop_resource_is_forbidden(): void
    {
        $this->book(['resource_id' => $this->resourceNamed('하은', 'demo-pilates')->id])->assertForbidden();
    }

    public function test_required_fields_are_validated(): void
    {
        $this->postJson(route('admin.reservations.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'resource_id', 'service_ids', 'date', 'start_time']);
    }

    public function test_move_into_conflict_is_rejected_and_free_slot_works(): void
    {
        $this->book()->assertCreated();
        $id = $this->book(['start_time' => '12:00', 'customer_phone' => '010-2222-3333'])->json('id');

        $this->patchJson(route('admin.reservations.move', $id), ['start' => self::DATE.'T10:20:00+09:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start');

        $this->patchJson(route('admin.reservations.move', $id), ['start' => self::DATE.'T14:00:00+09:00'])->assertOk();

        $r = Reservation::findOrFail($id);
        $this->assertSame('14:00', $r->start_at->format('H:i'));
        $this->assertSame('14:30', $r->end_at->format('H:i'));
        $this->assertTrue(AuditLog::where('target_id', $id)->where('action', 'moved')->exists());
    }

    // 취소 → 그 자리에 다른 예약 → 취소를 되돌리면 거절
    public function test_restoring_canceled_booking_checks_slot_again(): void
    {
        $id = $this->book()->json('id');
        $this->patchJson(route('admin.reservations.status', $id), ['status' => 'canceled'])->assertOk();

        $this->book(['customer_phone' => '010-2222-3333'])->assertCreated();

        $this->patchJson(route('admin.reservations.status', $id), ['status' => 'confirmed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_canceled_booking_cannot_be_edited(): void
    {
        $id = $this->book()->json('id');
        $this->patchJson(route('admin.reservations.status', $id), ['status' => 'canceled']);

        $this->putJson(route('admin.reservations.update', $id), [
            'customer_name' => '테스트',
            'customer_phone' => '010-1234-5678',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => self::DATE,
            'start_time' => '15:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    // 노쇼가 되면 고객 노쇼 횟수 +1, 다시 완료로 바꾸면 -1
    public function test_no_show_count_follows_status(): void
    {
        $id = $this->book()->json('id');
        $customer = Reservation::findOrFail($id)->customer;

        $this->patchJson(route('admin.reservations.status', $id), ['status' => 'no_show'])->assertOk();
        $this->assertSame(1, $customer->fresh()->no_show_count);

        $this->patchJson(route('admin.reservations.status', $id), ['status' => 'completed'])->assertOk();
        $this->assertSame(0, $customer->fresh()->no_show_count);
    }

    public function test_detail_masks_phone_and_shows_logs(): void
    {
        $id = $this->book()->json('id');

        $this->getJson(route('admin.reservations.show', $id))
            ->assertOk()
            ->assertJsonPath('customer.phone', '010-****-5678')
            ->assertJsonCount(1, 'logs');
    }

    public function test_other_shop_cannot_see_reservation(): void
    {
        $id = $this->book()->json('id');

        $this->actingAs($this->staff('pilates@notedot.test'), 'admin')
            ->getJson(route('admin.reservations.show', $id))
            ->assertNotFound();
    }
}
