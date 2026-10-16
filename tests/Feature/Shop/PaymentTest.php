<?php

namespace Tests\Feature\Shop;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Shop;
use App\Services\Payment\DemoGateway;
use App\Services\Payment\PortOneGateway;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// 보증금 결제: 데모 결제 승인·실패, 다른 브라우저 접근 막기, 시간 초과 자동 취소, 포트원 확인(금액 위조·상점 불일치), 웹훅 서명, 취소 규정 환불
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const STORE = 'store-test-0000';

    private const WEBHOOK_SECRET = 'whsec_dGVzdC13ZWJob29rLXNlY3JldC0xMjM0NTY3ODkw';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));
    }

    // 디지털 펌: 120,000원, 보증금 20,000원
    private function bookDeposit(string $phone = '010-5555-1234'): TestResponse
    {
        return $this->postJson(route('shop.booking.store', 'demo-hair'), [
            'service_id' => $this->serviceNamed('디지털 펌')->id,
            'resource_id' => $this->resourceNamed('지수')->id,
            'date' => '2030-01-08',
            'start_time' => '10:00',
            'customer_name' => '손님',
            'customer_phone' => $phone,
            'privacy' => true,
        ])->assertCreated();
    }

    private function payment(): Payment
    {
        return Payment::with('reservation')->latest('id')->firstOrFail();
    }

    private function usePortOne(): void
    {
        config([
            'payment.driver' => 'portone',
            'payment.portone.api_url' => 'https://portone.test',
            'payment.portone.store_id' => self::STORE,
            'payment.portone.channel_key' => 'channel-key-test',
            'payment.portone.api_secret' => 'api-secret-test',
            'payment.portone.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    private function fakePortOne(array $payment, int $status = 200): void
    {
        Http::fake([
            'portone.test/payments/*/cancel' => Http::response(['cancellation' => ['status' => 'SUCCEEDED']]),
            'portone.test/payments/*' => Http::response($payment, $status),
        ]);
    }

    public function test_booking_creates_ready_payment_with_server_amount(): void
    {
        $this->bookDeposit();

        $p = $this->payment();
        $this->assertSame(20000, $p->amount);
        $this->assertSame('ready', $p->status);
        $this->assertSame('demo', $p->provider);
        $this->assertSame('pending', $p->reservation->status);

        $this->get(route('shop.pay.show', ['demo-hair', $p->payment_key]))->assertOk()->assertSee('20,000원')->assertSee('데모 결제창');
    }

    public function test_demo_payment_confirms_reservation(): void
    {
        $this->bookDeposit();
        $p = $this->payment();

        $this->postJson(route('shop.pay.demo', ['demo-hair', $p->payment_key]), ['result' => 'paid'])
            ->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('redirect', route('shop.booking.done', 'demo-hair'));

        $p->refresh();
        $this->assertSame('paid', $p->status);
        $this->assertNotNull($p->paid_at);
        $this->assertSame('confirmed', $p->reservation->refresh()->status);
        $this->get(route('shop.booking.done', 'demo-hair'))->assertSee('예약이 확정되었습니다')->assertSee('결제 완료');

        // 두 번 확인해도 그대로 (두 번 처리하지 않음)
        $this->postJson(route('shop.pay.complete', ['demo-hair', $p->payment_key]))->assertJsonPath('status', 'paid');
        $this->assertSame(1, $p->reservation->payments()->count());
    }

    public function test_failed_payment_cancels_reservation_and_frees_slot(): void
    {
        $this->bookDeposit();
        $p = $this->payment();

        $this->postJson(route('shop.pay.demo', ['demo-hair', $p->payment_key]), ['result' => 'failed'])->assertJsonPath('status', 'failed');

        $this->assertSame('canceled', $p->reservation->refresh()->status);
        $this->bookDeposit('010-7777-8888');   // 같은 시간을 다른 손님이 잡을 수 있다
    }

    // 다른 브라우저(세션)에서는 남의 결제 화면을 열 수 없다
    public function test_other_browser_cannot_open_payment(): void
    {
        $this->bookDeposit();
        $key = $this->payment()->payment_key;
        $this->flushSession();

        $this->get(route('shop.pay.show', ['demo-hair', $key]))->assertNotFound();
        $this->postJson(route('shop.pay.demo', ['demo-hair', $key]), ['result' => 'paid'])->assertNotFound();
    }

    public function test_unpaid_reservation_expires_after_hold_time(): void
    {
        $this->bookDeposit();
        $p = $this->payment();

        $this->travel(14)->minutes();
        $this->artisan('payments:expire')->assertSuccessful();
        $this->assertSame('ready', $p->refresh()->status);

        $this->travel(2)->minutes();
        $this->artisan('payments:expire')->assertSuccessful();

        $p->refresh();
        $this->assertSame('failed', $p->status);
        $this->assertSame('결제 시간 초과', $p->failed_reason);
        $this->assertSame('canceled', $p->reservation->status);
        $this->get(route('shop.booking.done', 'demo-hair'))->assertSee('예약이 취소되었습니다');
    }

    // 시간 초과 직전에 결제했으면, 정리할 때 PG 에 다시 물어보고 확정한다
    public function test_expire_rechecks_gateway_first(): void
    {
        $this->bookDeposit();
        $p = $this->payment();
        app(DemoGateway::class)->simulate($p, 'paid');

        $this->travel(20)->minutes();
        $this->artisan('payments:expire');

        $this->assertSame('paid', $p->refresh()->status);
        $this->assertSame('confirmed', $p->reservation->refresh()->status);
    }

    // ── 포트원 ──

    public function test_portone_paid_payment_confirms(): void
    {
        $this->usePortOne();
        $this->bookDeposit();
        $p = $this->payment();
        $this->assertSame('portone', $p->provider);
        $this->fakePortOne(['status' => 'PAID', 'storeId' => self::STORE, 'amount' => ['total' => 20000], 'method' => ['type' => 'PaymentMethodCard'], 'pgTxId' => 'pg-1', 'receiptUrl' => 'https://receipt.test/1']);

        $this->postJson(route('shop.pay.complete', ['demo-hair', $p->payment_key]))->assertJsonPath('status', 'paid');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://portone.test/payments/'.$p->payment_key && $r->hasHeader('Authorization', 'PortOne api-secret-test'));
        $p->refresh();
        $this->assertSame(['paid', 'pg-1', 'PaymentMethodCard'], [$p->status, $p->pg_tx_id, $p->method]);
        $this->assertSame('confirmed', $p->reservation->refresh()->status);
    }

    // 결제창에서 금액을 바꿔 100원만 냈다면 → 받은 돈은 돌려주고 실패, 예약 취소
    public function test_portone_amount_mismatch_is_refunded_and_failed(): void
    {
        $this->usePortOne();
        $this->bookDeposit();
        $p = $this->payment();
        $this->fakePortOne(['status' => 'PAID', 'storeId' => self::STORE, 'amount' => ['total' => 100]]);

        $this->postJson(route('shop.pay.complete', ['demo-hair', $p->payment_key]))->assertJsonPath('status', 'failed');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/cancel') && $r['amount'] === 100);
        $this->assertSame('canceled', $p->reservation->refresh()->status);
    }

    public function test_portone_other_store_payment_is_rejected(): void
    {
        $this->usePortOne();
        $this->bookDeposit();
        $p = $this->payment();
        $this->fakePortOne(['status' => 'PAID', 'storeId' => 'someone-else', 'amount' => ['total' => 20000]]);

        $this->postJson(route('shop.pay.complete', ['demo-hair', $p->payment_key]))->assertJsonPath('status', 'failed');
        $this->assertNotSame('confirmed', $p->reservation->refresh()->status);
    }

    // 결제창을 열기만 하고 안 냈으면(조회 404) 그대로 대기
    public function test_portone_not_found_stays_ready(): void
    {
        $this->usePortOne();
        $this->bookDeposit();
        $p = $this->payment();
        $this->fakePortOne([], 404);

        $this->postJson(route('shop.pay.complete', ['demo-hair', $p->payment_key]))
            ->assertJsonPath('status', 'ready')->assertJsonPath('redirect', null);
    }

    private function webhook(Payment $p, string $secret = self::WEBHOOK_SECRET, ?int $ts = null): TestResponse
    {
        $body = json_encode(['type' => 'Transaction.Paid', 'timestamp' => '2030-01-06T08:00:00Z', 'data' => ['paymentId' => $p->payment_key, 'storeId' => self::STORE]]);
        $id = 'msg_1';
        $ts ??= time();
        $sig = base64_encode(hash_hmac('sha256', "{$id}.{$ts}.{$body}", base64_decode(substr($secret, 6)), true));

        return $this->call('POST', route('payments.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $ts,
            'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$sig,
        ], $body);
    }

    // 손님이 결제 뒤 창을 닫아도 웹훅으로 확정된다. 서명이 틀리거나 오래된 웹훅은 거절
    public function test_webhook_confirms_with_valid_signature_only(): void
    {
        $this->usePortOne();
        $this->bookDeposit();
        $p = $this->payment();
        $this->fakePortOne(['status' => 'PAID', 'storeId' => self::STORE, 'amount' => ['total' => 20000]]);

        $this->webhook($p, 'whsec_'.base64_encode('wrong-secret-wrong-secret'))->assertStatus(401);
        $this->webhook($p, ts: time() - 3600)->assertStatus(401);
        $this->assertSame('ready', $p->refresh()->status);

        $this->webhook($p)->assertOk();
        $this->assertSame('paid', $p->refresh()->status);
        $this->assertSame('confirmed', $p->reservation->refresh()->status);
    }

    public function test_portone_driver_needs_all_keys(): void
    {
        $this->usePortOne();
        $this->assertInstanceOf(PortOneGateway::class, app(PaymentGateway::class));

        config(['payment.portone.api_secret' => null]);
        $this->assertNotInstanceOf(PortOneGateway::class, app(PaymentGateway::class));
    }

    // ── 환불 ──

    private function paidReservation(): Reservation
    {
        $this->bookDeposit();
        $p = $this->payment();
        $this->postJson(route('shop.pay.demo', ['demo-hair', $p->payment_key]), ['result' => 'paid']);

        return $p->reservation->refresh();
    }

    // 기본 규정: 24시간 전 100%, 3시간 전 50%, 그 뒤 0%  (예약: 1/8 10:00)
    public function test_refund_policy_tiers(): void
    {
        $p = $this->paidReservation()->payment;
        $svc = app(PaymentService::class);

        $this->assertSame(['percent' => 100, 'amount' => 20000], $svc->suggestRefund($p, CarbonImmutable::parse('2030-01-07 09:00')));
        $this->assertSame(['percent' => 50, 'amount' => 10000], $svc->suggestRefund($p, CarbonImmutable::parse('2030-01-07 12:00')));
        $this->assertSame(['percent' => 0, 'amount' => 0], $svc->suggestRefund($p, CarbonImmutable::parse('2030-01-08 08:00')));

        $this->shop()->update(['cancel_policy' => ['tiers' => [['hours' => 48, 'percent' => 80]]]]);
        $this->assertSame(['percent' => 80, 'amount' => 16000], $svc->suggestRefund($p->fresh(), CarbonImmutable::parse('2030-01-06 09:00')));
    }

    public function test_owner_cancels_with_refund(): void
    {
        $r = $this->paidReservation();

        $this->actingAs($this->staff(), 'admin')
            ->getJson(route('admin.reservations.show', $r))
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.suggest.percent', 100);

        $this->patchJson(route('admin.reservations.status', $r), ['status' => 'canceled', 'reason' => '고객 요청', 'refund_amount' => 15000])
            ->assertOk()->assertJsonPath('message', '취소(으)로 바꿨습니다. 보증금 15,000원을 환불했습니다.');

        $p = $r->payment()->first();
        $this->assertSame(['partial_refunded', 15000], [$p->status, $p->refunded_amount]);

        // 남은 5,000원만 더 돌려줄 수 있다
        $this->postJson(route('admin.reservations.refund', $r), ['amount' => 6000])->assertUnprocessable();
        $this->postJson(route('admin.reservations.refund', $r), ['amount' => 5000])->assertOk();
        $this->assertSame('canceled', $p->refresh()->status);
    }

    public function test_staff_cannot_refund(): void
    {
        $r = $this->paidReservation();
        $minho = $this->resourceNamed('민호');
        $r->update(['resource_id' => $minho->id]);   // 직원(민호)이 볼 수 있는 예약으로

        $this->actingAs($this->staff('staff@notedot.test'), 'admin');
        $this->postJson(route('admin.reservations.refund', $r), ['amount' => 1000])->assertForbidden();
        $this->patchJson(route('admin.reservations.status', $r), ['status' => 'canceled', 'refund_amount' => 1000])->assertForbidden();
        $this->assertSame('confirmed', $r->refresh()->status);
    }

    // 취소 뒤에 늦게 결제가 들어오면 자동으로 전액 환불
    public function test_late_payment_after_cancel_is_refunded(): void
    {
        $this->bookDeposit();
        $p = $this->payment();
        app(ReservationService::class)->changeStatus($p->reservation, null, 'canceled', '고객 요청');

        $this->postJson(route('shop.pay.demo', ['demo-hair', $p->payment_key]), ['result' => 'paid']);

        $p->refresh();
        $this->assertSame(['canceled', 20000], [$p->status, $p->refunded_amount]);
    }

    public function test_cancel_policy_saved_in_settings(): void
    {
        $shop = $this->shop();
        $hours = collect(Shop::DAY_KEYS)->map(fn () => ['closed' => 0, 'open' => '10:00', 'close' => '20:00'])->all();

        $this->actingAs($this->staff(), 'admin')->put(route('admin.settings.shop.update'), [
            'name' => $shop->name, 'industry' => $shop->industry, 'slot_unit' => 30, 'theme' => 'auto', 'point_rate' => 0, 'hours' => $hours,
            'policy' => [['hours' => 3, 'percent' => 30], ['hours' => 48, 'percent' => 100], ['hours' => '', 'percent' => '']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['tiers' => [['hours' => 48, 'percent' => 100], ['hours' => 3, 'percent' => 30]]], $shop->refresh()->cancel_policy);
    }
}
