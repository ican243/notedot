<?php

namespace Tests\Feature;

use App\Contracts\MessageSender;
use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Models\Shop;
use App\Services\ClassSessionService;
use App\Services\Messaging\SolapiSender;
use App\Services\NotificationService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 고객 알림: 확정·취소·전날 리마인드, 매장 설정으로 끄기, 방문 예약 제외, 중복 방지, 솔라피 발송·실패·다시 보내기, 화면
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));
    }

    private function reservation(array $data = []): Reservation
    {
        return app(ReservationService::class)->create($this->shop(), null, $data + [
            'customer_name' => '손님', 'customer_phone' => '010-1111-2222',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07', 'start_time' => '10:00',
        ]);
    }

    private function useSolapi(): void
    {
        config([
            'notification.driver' => 'solapi',
            'notification.solapi.api_url' => 'https://solapi.test',
            'notification.solapi.api_key' => 'KEY',
            'notification.solapi.api_secret' => 'SECRET',
            'notification.solapi.sender' => '0212345678',
        ]);
    }

    public function test_confirmed_booking_sends_message(): void
    {
        $r = $this->reservation();

        $log = NotificationLog::sole();
        $this->assertSame(['confirmed', 'sent', 'log', '01011112222'], [$log->type, $log->status, $log->channel, $log->to_phone]);
        $this->assertSame($r->id, $log->reservation_id);
        $this->assertStringContainsString('[노트닷 헤어] 손님님, 1/7(월) 10:00 남성 커트 예약이 확정되었습니다.', $log->message);
        $this->assertStringContainsString('디자이너: 지수', $log->message);   // 업종 용어
    }

    public function test_cancel_sends_message_with_reason(): void
    {
        $r = $this->reservation();
        app(ReservationService::class)->changeStatus($r, null, 'canceled', '고객 요청');

        $log = NotificationLog::where('type', 'canceled')->sole();
        $this->assertStringContainsString('예약이 취소되었습니다.', $log->message);
        $this->assertStringContainsString('사유: 고객 요청', $log->message);
    }

    // 대기(보증금 결제 전)는 보내지 않고, 결제로 확정될 때 보낸다
    public function test_pending_waits_until_confirmed(): void
    {
        $r = $this->reservation(['status' => 'pending']);
        $this->assertSame(0, NotificationLog::count());

        app(ReservationService::class)->changeStatus($r, null, 'confirmed');
        $this->assertSame(['confirmed'], NotificationLog::pluck('type')->all());
    }

    public function test_walk_in_and_disabled_settings_skip(): void
    {
        $this->reservation(['channel' => 'walk_in']);
        $this->assertSame(0, NotificationLog::count());

        $this->shop()->update(['notification_settings' => ['confirmed' => false]]);
        $this->reservation(['start_time' => '11:00']);
        $this->assertSame(0, NotificationLog::count());
    }

    public function test_group_session_cancel_notifies_attendees(): void
    {
        $shop = $this->shop('demo-pilates');
        $session = app(ClassSessionService::class)->create($shop, null, [
            'resource_id' => $this->resourceNamed('하은', 'demo-pilates')->id,
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'date' => '2030-01-07', 'start_time' => '10:00',
        ])->first();
        foreach (['010-1000-0001', '010-1000-0002'] as $phone) {
            app(ReservationService::class)->bookSession($session, null, ['customer_name' => 'A', 'customer_phone' => $phone]);
        }

        app(ClassSessionService::class)->cancel($session, null, '강사 사정');

        $this->assertSame(2, NotificationLog::where('type', 'canceled')->count());
        $this->assertStringContainsString('사유: 강사 사정', NotificationLog::where('type', 'canceled')->first()->message);
    }

    // 리마인드: 매장 시각(18:00) 전에는 안 보내고, 뒤에는 내일 예약마다 한 번만. 방금 한 예약은 건너뜀
    public function test_reminders_once_after_shop_time(): void
    {
        $this->reservation();   // 1/7 10:00, 1/6 08:00 에 예약
        $svc = app(NotificationService::class);

        $this->assertSame(0, $svc->sendReminders(CarbonImmutable::parse('2030-01-06 17:50')));
        $this->assertSame(1, $svc->sendReminders(CarbonImmutable::parse('2030-01-06 18:00')));
        $this->assertSame(0, $svc->sendReminders(CarbonImmutable::parse('2030-01-06 18:10')));   // 두 번째는 중복이라 안 보냄

        $this->travelTo(CarbonImmutable::parse('2030-01-06 17:30'));
        $this->reservation(['start_time' => '12:00', 'customer_phone' => '010-3333-4444']);   // 방금 예약
        $this->assertSame(0, $svc->sendReminders(CarbonImmutable::parse('2030-01-06 18:20')));

        $log = NotificationLog::where('type', 'reminder')->sole();
        $this->assertStringContainsString('내일 1/7(월) 10:00 남성 커트 예약이 있습니다.', $log->message);
    }

    public function test_reminder_command_runs(): void
    {
        $this->reservation();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 19:00'));

        $this->artisan('notifications:remind')->expectsOutput('리마인드 1건')->assertSuccessful();
    }

    // ── 솔라피 ──

    public function test_solapi_sends_with_hmac_header(): void
    {
        $this->useSolapi();
        Http::fake(['solapi.test/*' => Http::response(['statusCode' => '2000', 'messageId' => 'M1'])]);

        $this->reservation();

        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://solapi.test/messages/v4/send'
                && preg_match('/^HMAC-SHA256 apiKey=KEY, date=\S+Z, salt=[0-9a-f]{32}, signature=[0-9a-f]{64}$/', $r->header('Authorization')[0])
                && $r['message']['to'] === '01011112222'
                && $r['message']['from'] === '0212345678'
                && ! isset($r['message']['kakaoOptions']);   // 템플릿이 없으면 문자
        });
        $log = NotificationLog::sole();
        $this->assertSame(['sent', 'sms', 'M1'], [$log->status, $log->channel, $log->provider_message_id]);
    }

    public function test_solapi_signature_is_correct(): void
    {
        $sender = new SolapiSender(['api_key' => 'K', 'api_secret' => 'S']);
        preg_match('/date=(\S+), salt=(\w+), signature=(\w+)/', $sender->authHeader(), $m);

        $this->assertSame(hash_hmac('sha256', $m[1].$m[2], 'S'), $m[3]);
    }

    public function test_solapi_uses_alimtalk_when_template_set(): void
    {
        $this->useSolapi();
        config(['notification.solapi.pf_id' => 'PF1', 'notification.solapi.templates.confirmed' => 'TPL1']);
        Http::fake(['solapi.test/*' => Http::response(['statusCode' => '2000', 'messageId' => 'M2'])]);

        $this->reservation();

        Http::assertSent(fn (Request $r) => $r['message']['kakaoOptions'] === ['pfId' => 'PF1', 'templateId' => 'TPL1', 'disableSms' => false]);
        $this->assertSame('alimtalk', NotificationLog::sole()->channel);
    }

    // 실패는 기록하고, 예약은 그대로. 다시 보내기로 성공
    public function test_failure_is_recorded_and_retry_works(): void
    {
        $this->useSolapi();
        Http::fake(['solapi.test/*' => Http::sequence()
            ->push(['errorCode' => 'ValidationError', 'errorMessage' => '발신번호 미등록'], 400)
            ->push(['statusCode' => '2000', 'messageId' => 'M3'])]);

        $r = $this->reservation();
        $log = NotificationLog::sole();
        $this->assertSame(['failed', '발신번호 미등록'], [$log->status, $log->error]);
        $this->assertSame('confirmed', $r->refresh()->status);

        $this->actingAs($this->staff(), 'admin')
            ->post(route('admin.notifications.retry', $log))->assertRedirect();
        $this->assertSame(['sent', 'M3'], [$log->refresh()->status, $log->provider_message_id]);
    }

    public function test_solapi_needs_all_keys(): void
    {
        $this->useSolapi();
        $this->assertInstanceOf(SolapiSender::class, app(MessageSender::class));

        config(['notification.solapi.sender' => null]);
        $this->assertNotInstanceOf(SolapiSender::class, app(MessageSender::class));
    }

    // ── 화면 ──

    public function test_owner_sees_log_page_and_staff_cannot(): void
    {
        $this->reservation();

        $this->actingAs($this->staff(), 'admin')->get(route('admin.notifications.index', ['type' => 'confirmed']))
            ->assertOk()->assertSee('예약 확정')->assertSee('010-****-2222')->assertDontSee('010-1111-2222');

        $this->actingAs($this->staff('staff@notedot.test'), 'admin')->get(route('admin.notifications.index'))->assertForbidden();
    }

    public function test_other_shop_log_is_404(): void
    {
        $this->reservation();
        $log = NotificationLog::sole();

        $this->actingAs($this->staff('pilates@notedot.test'), 'admin')
            ->post(route('admin.notifications.retry', $log))->assertNotFound();
    }

    public function test_settings_save_notification_toggles(): void
    {
        $shop = $this->shop();
        $hours = collect(Shop::DAY_KEYS)->map(fn () => ['closed' => 0, 'open' => '10:00', 'close' => '20:00'])->all();

        $this->actingAs($this->staff(), 'admin')->put(route('admin.settings.shop.update'), [
            'name' => $shop->name, 'industry' => $shop->industry, 'slot_unit' => 30, 'theme' => 'auto', 'point_rate' => 0, 'hours' => $hours,
            'notify' => ['confirmed' => '1', 'canceled' => '0', 'reminder' => '1', 'reminder_at' => '20:30'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['confirmed' => true, 'canceled' => false, 'reminder' => true, 'reminder_at' => '20:30'], $shop->refresh()->notifySettings());
    }
}
