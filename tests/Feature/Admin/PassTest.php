<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Pass;
use App\Models\Reservation;
use App\Services\ClassSessionService;
use App\Services\PassService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// 회원권·정액권: 발급, 예약에 사용(횟수·금액), 잔액 부족·만료·전용 상품, 두 번 사용 막기, 사용 취소, 예약 취소 시 되돌림, 조정·권 취소
class PassTest extends TestCase
{
    use RefreshDatabase;

    private PassService $passes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));
        $this->passes = app(PassService::class);
    }

    private function reservation(string $time = '10:00', string $service = '남성 커트', string $phone = '010-1111-2222'): Reservation
    {
        return app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => '고객', 'customer_phone' => $phone,
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed($service)->id],
            'date' => '2030-01-07', 'start_time' => $time,
        ]);
    }

    private function pass(Customer $customer, array $data = []): Pass
    {
        return $this->passes->issue($customer, $this->staff(), $data + ['type' => 'count', 'name' => '10회권', 'price' => 200000, 'total' => 10]);
    }

    private function assertFails(callable $fn, string $text): void
    {
        try {
            $fn();
            $this->fail('막혀야 하는데 통과함');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($text, collect($e->errors())->flatten()->first());
        }
    }

    public function test_issue_records_first_transaction(): void
    {
        $customer = $this->reservation()->customer;
        $pass = $this->pass($customer);

        $this->assertSame(10, $pass->balance);
        $tx = $pass->transactions()->sole();
        $this->assertSame(['issue', 10, 10], [$tx->type, $tx->amount, $tx->balance_after]);
    }

    public function test_count_pass_uses_one(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer);

        $this->passes->useFor($r, $pass, $this->staff());

        $this->assertSame(9, $pass->refresh()->balance);
        $this->assertSame([$pass->id => 1], $this->passes->usedOn($r)->all());
    }

    public function test_amount_pass_uses_reservation_price(): void
    {
        $r = $this->reservation();   // 남성 커트 22,000원
        $pass = $this->pass($r->customer, ['type' => 'amount', 'name' => '정액권', 'total' => 50000]);

        $this->passes->useFor($r, $pass, null);

        $this->assertSame(28000, $pass->refresh()->balance);
    }

    public function test_same_reservation_cannot_use_twice(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer);
        $this->passes->useFor($r, $pass, null);

        $this->assertFails(fn () => $this->passes->useFor($r, $pass, null), '이미 회원권으로 처리한');
        $this->assertSame(9, $pass->refresh()->balance);
    }

    public function test_not_enough_balance(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer, ['type' => 'amount', 'name' => '정액권', 'total' => 10000]);

        $this->assertFails(fn () => $this->passes->useFor($r, $pass, null), '잔액이 부족합니다');
        $this->assertSame(10000, $pass->refresh()->balance);
    }

    public function test_expired_pass_is_rejected(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer, ['expires_on' => '2030-01-06']);
        $this->travel(1)->days();

        $this->assertFails(fn () => $this->passes->useFor($r, $pass, null), '만료된 권');
    }

    public function test_service_only_pass(): void
    {
        $r = $this->reservation();   // 남성 커트
        $pass = $this->pass($r->customer, ['service_id' => $this->serviceNamed('여성 커트')->id]);

        $this->assertFails(fn () => $this->passes->useFor($r, $pass, null), '여성 커트 전용');
        $this->assertCount(0, $this->passes->usableFor($r));
    }

    public function test_other_customers_pass_is_rejected(): void
    {
        $r = $this->reservation();
        $other = $this->reservation('11:00', phone: '010-9999-8888')->customer;
        $pass = $this->pass($other);

        $this->assertFails(fn () => $this->passes->useFor($r, $pass, null), '이 고객의 권이 아닙니다');
    }

    public function test_restore_gives_balance_back(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer);
        $this->passes->useFor($r, $pass, null);

        $this->assertTrue($this->passes->restoreFor($r, null));

        $this->assertSame(10, $pass->refresh()->balance);
        $this->assertSame([], $this->passes->usedOn($r)->all());
        $this->assertFalse($this->passes->restoreFor($r, null));   // 두 번째는 되돌릴 것 없음
        $this->passes->useFor($r, $pass, null);                    // 되돌린 뒤에는 다시 쓸 수 있다
        $this->assertSame(9, $pass->refresh()->balance);
    }

    public function test_canceling_reservation_restores_pass(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer);
        $this->passes->useFor($r, $pass, null);

        app(ReservationService::class)->changeStatus($r, null, 'canceled');

        $this->assertSame(10, $pass->refresh()->balance);
        $this->assertSame('예약 취소', $pass->transactions()->latest('id')->first()->memo);
    }

    // 노쇼는 차감을 그대로 둔다 (매장 규정상 1회 차감이 흔하다)
    public function test_no_show_keeps_usage(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer);
        $this->passes->useFor($r, $pass, null);

        app(ReservationService::class)->changeStatus($r, null, 'no_show');

        $this->assertSame(9, $pass->refresh()->balance);
    }

    public function test_group_booking_uses_party_size_and_session_cancel_restores(): void
    {
        $shop = $this->shop('demo-pilates');
        $session = app(ClassSessionService::class)->create($shop, null, [
            'resource_id' => $this->resourceNamed('하은', 'demo-pilates')->id,
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'date' => '2030-01-07', 'start_time' => '10:00',
        ])->first();
        $r = app(ReservationService::class)->bookSession($session, null, ['customer_name' => 'A', 'customer_phone' => '010-1111-2222', 'party_size' => 2]);
        $pass = $this->pass($r->customer);

        $this->passes->useFor($r, $pass, null);
        $this->assertSame(8, $pass->refresh()->balance);

        app(ClassSessionService::class)->cancel($session, null, '강사 사정');
        $this->assertSame(10, $pass->refresh()->balance);
    }

    public function test_adjust_and_cancel(): void
    {
        $pass = $this->pass($this->reservation()->customer);

        $this->passes->adjust($pass, null, -3, '이전 매장 사용분');
        $this->assertSame(7, $pass->refresh()->balance);
        $this->assertFails(fn () => $this->passes->adjust($pass, null, -8, '너무 많이'), '잔액보다 많이');

        $this->passes->cancel($pass, null, '환불');
        $pass->refresh();
        $this->assertSame(0, $pass->balance);
        $this->assertTrue($pass->isCanceled());
        $this->assertSame(['issue', 'adjust', 'cancel'], $pass->transactions()->orderBy('id')->pluck('type')->all());
        $this->assertSame(0, (int) $pass->transactions()->sum('amount'));   // 거래 합 = 잔액
    }

    // ── 화면(API) ──

    public function test_staff_uses_pass_from_reservation_window(): void
    {
        $r = $this->reservation();
        $pass = $this->pass($r->customer);

        $this->actingAs($this->staff(), 'admin')
            ->getJson(route('admin.reservations.show', $r))
            ->assertOk()
            ->assertJsonPath('passes.usable.0.id', $pass->id)
            ->assertJsonPath('passes.usable.0.need', '1회')
            ->assertJsonPath('customer.url', route('admin.customers.show', $r->customer));

        $this->postJson(route('admin.reservations.pass.store', $r), ['pass_id' => $pass->id])
            ->assertOk()->assertJsonPath('message', '10회권에서 1회 차감했습니다.');

        $this->getJson(route('admin.reservations.show', $r))
            ->assertJsonPath('passes.used.0.amount', '1회')
            ->assertJsonPath('passes.usable', []);

        $this->deleteJson(route('admin.reservations.pass.destroy', $r))->assertOk();
        $this->assertSame(10, $pass->refresh()->balance);
    }

    public function test_other_shop_pass_id_is_rejected(): void
    {
        $r = $this->reservation();
        $pilatesCustomer = $this->shop('demo-pilates')->customers()->create(['name' => 'P', 'phone' => '01077776666']);
        $foreign = $this->pass($pilatesCustomer);

        $this->actingAs($this->staff(), 'admin')
            ->postJson(route('admin.reservations.pass.store', $r), ['pass_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('pass_id');
    }

    public function test_only_owner_issues_passes(): void
    {
        $customer = $this->reservation()->customer;
        $form = ['type' => 'count', 'name' => '10회권', 'price' => 100000, 'total' => 10];

        $this->actingAs($this->staff('staff@notedot.test'), 'admin')
            ->post(route('admin.passes.store', $customer), $form)->assertForbidden();

        $this->actingAs($this->staff(), 'admin')
            ->post(route('admin.passes.store', $customer), $form)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $customer->passes()->count());
    }
}
