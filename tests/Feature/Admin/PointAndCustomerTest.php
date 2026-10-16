<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Models\Shop;
use App\Services\PassService;
use App\Services\PointService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// 포인트 적립·회수·조정 + 고객 관리 화면(목록·상세·수정)과 직원 권한
class PointAndCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));
        $this->shop()->update(['point_rate' => 5]);
    }

    private function reservation(string $resource = '지수', string $phone = '010-1111-2222'): Reservation
    {
        return app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => '고객', 'customer_phone' => $phone,
            'resource_id' => $this->resourceNamed($resource)->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],   // 22,000원
            'date' => '2030-01-07', 'start_time' => '10:00',
        ]);
    }

    private function setStatus(Reservation $r, string $status): void
    {
        app(ReservationService::class)->changeStatus($r, null, $status);
    }

    public function test_completing_earns_and_undoing_revokes(): void
    {
        $r = $this->reservation();

        $this->setStatus($r, 'completed');
        $this->assertSame(1100, $r->customer->refresh()->points);   // 22,000 × 5%

        $this->setStatus($r, 'completed');   // 같은 상태로 다시 → 두 번 적립하지 않음
        $this->setStatus($r, 'confirmed');
        $this->assertSame(0, $r->customer->refresh()->points);
        $this->assertSame(['earn', 'revoke'], $r->customer->pointTransactions()->orderBy('id')->pluck('type')->all());
    }

    public function test_no_points_when_rate_is_zero(): void
    {
        $this->shop()->update(['point_rate' => 0]);
        $r = $this->reservation();

        $this->setStatus($r, 'completed');

        $this->assertSame(0, $r->customer->refresh()->points);
        $this->assertSame(0, $r->customer->pointTransactions()->count());
    }

    // 회원권으로 처리한 예약은 적립하지 않는다. 완료 뒤에 권으로 처리하면 적립을 회수
    public function test_pass_paid_reservation_does_not_earn(): void
    {
        $r = $this->reservation();
        $this->setStatus($r, 'completed');
        $pass = app(PassService::class)->issue($r->customer, null, ['type' => 'count', 'name' => '10회권', 'price' => 0, 'total' => 10]);

        app(PassService::class)->useFor($r, $pass, null);
        $this->assertSame(0, $r->customer->refresh()->points);

        app(PassService::class)->restoreFor($r, null);
        $this->assertSame(1100, $r->customer->refresh()->points);
    }

    // 적립 포인트를 이미 써 버렸으면 남은 만큼만 회수 (마이너스 잔액 없음)
    public function test_revoke_never_goes_negative(): void
    {
        $r = $this->reservation();
        $this->setStatus($r, 'completed');
        app(PointService::class)->adjust($r->customer, null, -1000, '사용');

        $this->setStatus($r, 'canceled');

        $this->assertSame(0, $r->customer->refresh()->points);
    }

    public function test_adjust_cannot_go_below_zero(): void
    {
        $customer = $this->reservation()->customer;

        $this->expectException(ValidationException::class);
        app(PointService::class)->adjust($customer, null, -1, '빼기');
    }

    // ── 화면 ──

    public function test_owner_sees_list_and_detail_with_money(): void
    {
        $r = $this->reservation();
        $this->setStatus($r, 'completed');

        $this->actingAs($this->staff(), 'admin')
            ->get(route('admin.customers.index', ['q' => '1111']))
            ->assertOk()->assertSee('고객')->assertSee('010-****-2222');

        $this->get(route('admin.customers.show', $r->customer))
            ->assertOk()
            ->assertSee('010-1111-2222')
            ->assertSee('총 이용 금액')
            ->assertSee('22,000원')
            ->assertSee('1,100P');
    }

    // 직원: 자기 resource 에 예약한 고객만, 번호는 가리고, 금액은 숨긴다
    public function test_staff_sees_only_own_customers_without_money(): void
    {
        $mine = $this->reservation('민호', '010-3333-4444');
        $other = $this->reservation('지수', '010-5555-6666');
        $this->actingAs($this->staff('staff@notedot.test'), 'admin');

        $this->get(route('admin.customers.index'))->assertOk()
            ->assertSee('010-****-4444')->assertDontSee('010-****-6666');

        $this->get(route('admin.customers.show', $mine->customer))->assertOk()
            ->assertSee('010-****-4444')->assertDontSee('010-3333-4444')
            ->assertDontSee('총 이용 금액')->assertDontSee('권 발급');

        $this->get(route('admin.customers.show', $other->customer))->assertForbidden();
        $this->post(route('admin.points.adjust', $mine->customer), ['amount' => 100, 'memo' => 'x'])->assertForbidden();
    }

    public function test_other_shop_customer_is_404(): void
    {
        $foreign = $this->shop('demo-pilates')->customers()->create(['name' => 'P', 'phone' => '01077776666']);

        $this->actingAs($this->staff(), 'admin')->get(route('admin.customers.show', $foreign))->assertNotFound();
    }

    public function test_update_memo_and_adjust_points_from_page(): void
    {
        $customer = $this->reservation()->customer;
        $this->actingAs($this->staff(), 'admin');

        $this->put(route('admin.customers.update', $customer), ['name' => '새이름', 'memo' => '두피 민감'])->assertRedirect();
        $this->assertSame(['새이름', '두피 민감'], [$customer->refresh()->name, $customer->memo]);

        $this->post(route('admin.points.adjust', $customer), ['amount' => 0, 'memo' => '없음'])->assertSessionHasErrors('amount');
        $this->post(route('admin.points.adjust', $customer), ['amount' => 500, 'memo' => '이벤트'])->assertSessionHasNoErrors();
        $this->assertSame(500, $customer->refresh()->points);
    }

    public function test_point_rate_saved_in_settings(): void
    {
        $shop = $this->shop();
        $hours = collect(Shop::DAY_KEYS)->map(fn () => ['closed' => 0, 'open' => '10:00', 'close' => '20:00'])->all();

        $this->actingAs($this->staff(), 'admin')->put(route('admin.settings.shop.update'), [
            'name' => $shop->name, 'industry' => $shop->industry, 'slot_unit' => 30, 'theme' => 'auto',
            'point_rate' => 7, 'hours' => $hours,
        ])->assertSessionHasNoErrors();

        $this->assertSame(7, $shop->refresh()->point_rate);
    }
}
