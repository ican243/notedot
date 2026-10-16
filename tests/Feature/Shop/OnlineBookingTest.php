<?php

namespace Tests\Feature\Shop;

use App\Models\ClassSession;
use App\Models\Reservation;
use App\Services\ClassSessionService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// 고객 예약 화면: 가능한 시간·일정 목록, 예약 저장(1:1·상관없음·공간·그룹), 동의 저장, 완료 화면
class OnlineBookingTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2030-01-07';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));
    }

    private function book(array $overrides = [], string $slug = 'demo-hair'): TestResponse
    {
        return $this->postJson(route('shop.booking.store', $slug), $overrides + [
            'service_id' => $this->serviceNamed('남성 커트')->id,
            'resource_id' => $this->resourceNamed('지수')->id,
            'date' => self::MONDAY,
            'start_time' => '10:00',
            'customer_name' => '손님',
            'customer_phone' => '010-5555-1234',
            'privacy' => true,
        ]);
    }

    private function groupSession(int $capacity = 6): ClassSession
    {
        return app(ClassSessionService::class)->create($this->shop('demo-pilates'), null, [
            'resource_id' => $this->resourceNamed('하은', 'demo-pilates')->id,
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'date' => self::MONDAY, 'start_time' => '10:00', 'capacity' => $capacity,
        ])->first();
    }

    public function test_booking_page_lists_services(): void
    {
        $this->get(route('shop.home', 'demo-hair'))->assertOk()->assertSee('남성 커트');
    }

    public function test_slots_endpoint_returns_booking_window(): void
    {
        $days = $this->getJson(route('shop.booking.slots', [
            'shop' => 'demo-hair', 'service_id' => $this->serviceNamed('남성 커트')->id,
        ]))->assertOk()->json('days');

        $this->assertCount(30, $days);
        $this->assertSame([], $days[0]['times']);              // 일요일 휴무
        $this->assertSame('10:00', $days[1]['times'][0]);      // 월요일
        $this->assertSame('월', $days[1]['weekday']);
    }

    public function test_customer_books_and_sees_done_page(): void
    {
        $this->book()->assertCreated()->assertJsonPath('redirect', route('shop.booking.done', 'demo-hair'));

        $r = Reservation::with('customer')->sole();
        $this->assertSame('online', $r->channel);
        $this->assertSame('confirmed', $r->status);
        $this->assertNull($r->created_by);
        $this->assertNotNull($r->customer->privacy_agreed_at);
        $this->assertNull($r->customer->marketing_agreed_at);

        $this->get(route('shop.booking.done', 'demo-hair'))
            ->assertOk()
            ->assertSee('예약이 확정되었습니다')
            ->assertSee('010-****-1234')
            ->assertDontSee('010-5555-1234');
    }

    public function test_marketing_consent_is_saved_separately(): void
    {
        $this->book(['marketing' => true])->assertCreated();

        $this->assertNotNull(Reservation::sole()->customer->marketing_agreed_at);
    }

    public function test_privacy_consent_is_required(): void
    {
        $this->book(['privacy' => false])->assertUnprocessable()->assertJsonValidationErrors('privacy');

        $this->assertSame(0, Reservation::count());
    }

    // 화면을 거치지 않고 근무 시간 밖·휴무일을 보내도 서버가 막는다
    public function test_time_outside_slots_is_rejected(): void
    {
        $this->book(['start_time' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->book(['date' => '2030-01-06', 'start_time' => '12:00'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->book(['start_time' => '10:15'])->assertUnprocessable()->assertJsonValidationErrors('start_time');   // 30분 단위 아님
    }

    public function test_taken_time_is_rejected(): void
    {
        $this->book()->assertCreated();

        $this->book(['customer_phone' => '010-5555-9999'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    // "상관없음": 지수가 차 있으면 같은 상품을 하는 민호에게
    public function test_any_resource_picks_free_one(): void
    {
        $this->book()->assertCreated();

        $this->book(['resource_id' => null, 'customer_phone' => '010-5555-9999'])->assertCreated();

        $this->assertSame('민호', Reservation::latest('id')->first()->resource->name);
    }

    // 보증금 상품: 예약은 대기 + 결제 대기, 다음 화면은 결제 화면
    public function test_deposit_service_goes_to_payment(): void
    {
        $res = $this->book(['service_id' => $this->serviceNamed('디지털 펌')->id])->assertCreated();

        $r = Reservation::with('payment')->sole();
        $this->assertSame('pending', $r->status);
        $this->assertSame('ready', $r->payment->status);
        $res->assertJsonPath('redirect', route('shop.pay.show', ['demo-hair', $r->payment->payment_key]));
        $this->get(route('shop.booking.done', 'demo-hair'))->assertSee('결제를 기다리고 있습니다');
    }

    public function test_space_party_size_is_limited(): void
    {
        $base = [
            'service_id' => $this->serviceNamed('2시간 이용', 'demo-studyroom')->id,
            'resource_id' => $this->resourceNamed('A룸 (4인)', 'demo-studyroom')->id,
            'start_time' => '14:00',
        ];

        $this->book($base + ['party_size' => 5], 'demo-studyroom')->assertUnprocessable()->assertJsonValidationErrors('party_size');
        $this->book($base + ['party_size' => 4], 'demo-studyroom')->assertCreated();
    }

    public function test_sessions_endpoint_shows_seats_left(): void
    {
        $session = $this->groupSession();
        app(ReservationService::class)->bookSession($session, null, ['customer_name' => 'A', 'customer_phone' => '010-1111-2222', 'party_size' => 2]);

        $this->getJson(route('shop.booking.sessions', [
            'shop' => 'demo-pilates', 'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
        ]))->assertOk()->assertJsonPath('0.id', $session->id)->assertJsonPath('0.left', 4);
    }

    public function test_customer_joins_group_session(): void
    {
        $session = $this->groupSession();

        $this->book([
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'resource_id' => null, 'date' => null, 'start_time' => null,
            'class_session_id' => $session->id, 'party_size' => 2,
        ], 'demo-pilates')->assertCreated();

        $r = Reservation::sole();
        $this->assertSame($session->id, $r->class_session_id);
        $this->assertSame(50000, $r->total_price);
    }

    public function test_full_session_is_rejected(): void
    {
        $session = $this->groupSession(capacity: 1);
        app(ReservationService::class)->bookSession($session, null, ['customer_name' => 'A', 'customer_phone' => '010-1111-2222']);

        $this->book([
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'resource_id' => null, 'date' => null, 'start_time' => null,
            'class_session_id' => $session->id,
        ], 'demo-pilates')->assertUnprocessable()->assertJsonValidationErrors('party_size');
    }

    public function test_other_shop_service_is_rejected(): void
    {
        $this->book(['service_id' => $this->serviceNamed('1시간 이용', 'demo-studyroom')->id])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_done_page_without_booking_goes_home(): void
    {
        $this->get(route('shop.booking.done', 'demo-hair'))->assertRedirect(route('shop.home', 'demo-hair'));
    }

    public function test_unknown_shop_is_404(): void
    {
        $this->get(route('shop.home', 'no-such-shop'))->assertNotFound();
    }
}
