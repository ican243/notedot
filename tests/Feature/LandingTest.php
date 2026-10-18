<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 첫 주소: 데모 모드면 안내 화면(매장 3곳·관리자 계정), 아니면 관리자 로그인. 데모 계정 링크는 로그인 칸을 채운다
class LandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
    }

    public function test_demo_mode_shows_landing(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['노트닷 헤어', '노트닷 필라테스', '노트닷 스터디룸'])
            ->assertSee('디자이너 · 시술 · 고객')
            ->assertSee('시술 → 디자이너 → 날짜·시간')
            ->assertSee('강사 또는 시간표')
            ->assertSee(route('shop.home', 'demo-pilates'))
            ->assertSee(route('admin.login', ['email' => 'staff@notedot.test']), false)
            ->assertSee('계절 테마 바꾸기');
    }

    public function test_landing_has_try_buttons_role_cards_and_notice(): void
    {
        config(['demo.enabled' => true]);

        $html = $this->get('/')->assertOk()
            ->assertSee('노트닷 헤어 예약 체험하기')
            ->assertSee('노트닷 스터디룸 예약 체험하기')
            ->assertSeeInOrder(['data-role="owner"', '노트닷 헤어 대표로 체험', 'data-role="staff"', '노트닷 헤어 직원으로 체험'], false)
            ->assertSee('본인 담당 디자이너만')
            ->assertSee('매일 '.config('demo.reset_at').' 초기화')
            ->assertSee('실제 결제 없음')
            ->assertSee('문자 발송 없음')
            ->getContent();

        // 다른 업종 대표 링크 2개 (필라테스·스터디룸)
        $this->assertStringContainsString(e(route('admin.login', ['email' => 'pilates@notedot.test'])), $html);
        $this->assertStringContainsString(e(route('admin.login', ['email' => 'studyroom@notedot.test'])), $html);
    }

    public function test_notice_follows_connected_payment_and_message_drivers(): void
    {
        config(['demo.enabled' => true, 'payment.driver' => 'portone', 'notification.driver' => 'solapi',
            'payment.portone.store_id' => 's', 'payment.portone.channel_key' => 'c', 'payment.portone.api_secret' => 'a',
            'notification.solapi.api_key' => 'k', 'notification.solapi.api_secret' => 's', 'notification.solapi.sender' => '0212345678']);

        // 실제 키가 연결되면 "결제 없음·발송 없음"이라고 말하지 않는다
        $this->get('/')->assertOk()->assertSee('테스트 결제')->assertDontSee('실제 결제 없음')->assertDontSee('문자 발송 없음');
    }

    public function test_without_demo_mode_goes_to_login(): void
    {
        $this->get('/')->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertDontSee('계절 테마 바꾸기');   // 전환 버튼도 데모에서만
    }

    public function test_login_email_is_prefilled_and_escaped(): void
    {
        $this->get(route('admin.login', ['email' => 'pilates@notedot.test']))->assertSee('value="pilates@notedot.test"', false);
        $this->get(route('admin.login', ['email' => '"><script>x</script>']))->assertDontSee('<script>x</script>', false);
        $this->get(route('admin.login').'?email[]=a')->assertOk();   // 배열을 보내도 오류 없이
    }

    public function test_season_switch_on_admin_and_shop_pages_in_demo(): void
    {
        config(['demo.enabled' => true]);

        $this->get(route('shop.home', 'demo-hair'))->assertOk()->assertSee('seasonSwitch', false)->assertSee("localStorage.getItem('notedot.season')", false);
        $this->actingAs($this->staff(), 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee('seasonSwitch', false);
    }
}
