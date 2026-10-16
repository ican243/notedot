<?php

namespace Tests\Feature;

use App\Services\ClassSessionService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// 실시간 알림: 예약·일정이 바뀌면 Node 서버로 보내기, 출입증(JWT), poll 방식, Node 가 죽어도 예약은 저장
class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-publish-secret-0123456789abcdef';

    private const JWT_SECRET = 'test-jwt-secret-0123456789abcdefghij';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));
    }

    private function useNode(): void
    {
        config([
            'realtime.driver' => 'node',
            'realtime.server_url' => 'http://realtime.test',
            'realtime.secret' => self::SECRET,
            'realtime.jwt_secret' => self::JWT_SECRET,
        ]);
        Http::fake(['realtime.test/*' => Http::response(null, 204)]);
    }

    private function bookOnline(string $phone = '010-5555-1234'): void
    {
        $this->postJson(route('shop.booking.store', 'demo-hair'), [
            'service_id' => $this->serviceNamed('남성 커트')->id,
            'resource_id' => $this->resourceNamed('지수')->id,
            'date' => '2030-01-07',
            'start_time' => '10:00',
            'customer_name' => '손님',
            'customer_phone' => $phone,
            'privacy' => true,
        ])->assertCreated();
    }

    /** @return array<int, array> 보낸 메시지 본문들 */
    private function published(): array
    {
        return Http::recorded()->map(fn ($pair) => json_decode($pair[0]->body(), true))->values()->all();
    }

    public function test_online_booking_notifies_admin_and_public_rooms(): void
    {
        $this->useNode();

        $this->bookOnline();

        Http::assertSent(fn (Request $r) => $r->url() === 'http://realtime.test/publish'
            && $r->hasHeader('X-Realtime-Secret', self::SECRET));

        [$admin, $public] = $this->published();
        $shopId = $this->shop()->id;

        $this->assertSame(['shop_id' => $shopId, 'audience' => 'admin', 'event' => 'reservation.changed'], array_slice($admin, 0, 3));
        $this->assertSame('created', $admin['data']['action']);
        $this->assertSame('online', $admin['data']['channel']);
        $this->assertSame('지수', $admin['data']['resource']);
        $this->assertSame('2030-01-07 10:00', $admin['data']['start']);

        $this->assertSame('public', $public['audience']);
        $this->assertSame(['kind' => 'time', 'date' => '2030-01-07'], $public['data']);
    }

    // 고객 화면(공개 룸)에도, 관리자 화면에도 이름·전화번호는 보내지 않는다
    public function test_messages_have_no_customer_details(): void
    {
        $this->useNode();

        $this->bookOnline('010-5555-7777');

        $raw = json_encode($this->published(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('손님', $raw);
        $this->assertStringNotContainsString('7777', $raw);
    }

    public function test_admin_changes_carry_who_did_it(): void
    {
        $this->useNode();
        $owner = $this->staff();

        $reservation = app(ReservationService::class)->create($this->shop(), $owner, [
            'customer_name' => 'A', 'customer_phone' => '010-1111-2222',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07', 'start_time' => '11:00',
        ]);
        app(ReservationService::class)->changeStatus($reservation, $owner, 'canceled');

        $admin = collect($this->published())->where('audience', 'admin')->values();
        $this->assertSame(['created', 'status'], $admin->pluck('data.action')->all());
        $this->assertSame([$owner->id, $owner->id], $admin->pluck('data.by')->all());
        $this->assertSame('canceled', $admin[1]['data']['status']);
    }

    // 실패해서 되돌린(rollback) 예약은 알리지 않는다 (commit 된 뒤에만 보냄)
    public function test_failed_booking_sends_nothing(): void
    {
        $this->useNode();
        $this->bookOnline();
        Http::fake(['realtime.test/*' => Http::response(null, 204)]);   // 기록 비우기

        try {
            app(ReservationService::class)->create($this->shop(), null, [
                'customer_name' => 'B', 'customer_phone' => '010-3333-4444',
                'resource_id' => $this->resourceNamed('지수')->id,
                'service_ids' => [$this->serviceNamed('남성 커트')->id],
                'date' => '2030-01-07', 'start_time' => '10:00',
            ]);
            $this->fail('겹치는 예약이 저장됨');
        } catch (ValidationException) {
        }

        Http::assertNothingSent();
    }

    public function test_repeated_sessions_send_one_message(): void
    {
        $this->useNode();

        app(ClassSessionService::class)->create($this->shop('demo-pilates'), null, [
            'resource_id' => $this->resourceNamed('하은', 'demo-pilates')->id,
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'date' => '2030-01-07', 'start_time' => '10:00',
            'repeat_days' => [1, 3], 'repeat_weeks' => 2,
        ]);

        $sent = $this->published();
        $this->assertCount(2, $sent);   // 관리자 1 + 공개 1
        $this->assertSame('session.changed', $sent[0]['event']);
        $this->assertSame(4, $sent[0]['data']['count']);
        $this->assertSame('all', $sent[1]['data']['kind']);
    }

    // Node 서버가 꺼져 있어도 예약은 저장되고 기록만 남는다
    public function test_booking_succeeds_when_node_is_down(): void
    {
        $this->useNode();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        Log::spy();

        $this->bookOnline();

        $this->assertSame(1, $this->shop()->reservations()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => $msg === 'realtime publish failed')->twice();
    }

    public function test_poll_driver_sends_nothing(): void
    {
        Http::fake();

        $this->bookOnline();

        Http::assertNothingSent();
    }

    public function test_staff_token_is_for_own_shop_admin_room(): void
    {
        $this->useNode();
        $staff = $this->staff();

        $token = $this->actingAs($staff, 'admin')->getJson(route('admin.realtime.token'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('token');

        $claims = JWT::decode($token, new Key(self::JWT_SECRET, 'HS256'));
        $this->assertSame('admin', $claims->aud);
        $this->assertSame($staff->shop_id, $claims->shop);
        $this->assertSame((string) $staff->id, $claims->sub);
        $this->assertSame('notedot', $claims->iss);
        $this->assertLessThanOrEqual(60, $claims->exp - $claims->iat);
    }

    public function test_guest_cannot_get_admin_token(): void
    {
        $this->useNode();

        $this->getJson(route('admin.realtime.token'))->assertUnauthorized();
    }

    public function test_public_token_is_for_public_room(): void
    {
        $this->useNode();

        $token = $this->getJson(route('shop.realtime.token', 'demo-hair'))->assertOk()->json('token');

        $claims = JWT::decode($token, new Key(self::JWT_SECRET, 'HS256'));
        $this->assertSame('public', $claims->aud);
        $this->assertSame($this->shop()->id, $claims->shop);
    }

    public function test_tokens_are_off_in_poll_mode(): void
    {
        $this->getJson(route('shop.realtime.token', 'demo-hair'))->assertNotFound();
        $this->actingAs($this->staff(), 'admin')->getJson(route('admin.realtime.token'))->assertNotFound();
    }

    public function test_pages_receive_realtime_config(): void
    {
        $this->useNode();

        $this->get(route('shop.home', 'demo-hair'))->assertOk()->assertViewHas('config', fn ($c) => $c['realtime']['driver'] === 'node'
            && $c['realtime']['path'] === '/socket.io'
            && $c['realtime']['tokenUrl'] === route('shop.realtime.token', 'demo-hair'));

        $this->actingAs($this->staff(), 'admin')->get(route('admin.calendar'))->assertOk()
            ->assertViewHas('config', fn ($c) => $c['realtime']['driver'] === 'node' && $c['me'] === $this->staff()->id);
    }
}
