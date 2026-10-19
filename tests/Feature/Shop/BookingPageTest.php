<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 손님 예약 화면 모양: 짧은 상단(배너 없음), 고르기 → [다음], 취소 규정은 서버 규정 그대로
class BookingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
    }

    public function test_booking_page_is_short_header_with_next_button_and_policy(): void
    {
        $this->shop()->update(['cancel_policy' => ['tiers' => [['hours' => 48, 'percent' => 100], ['hours' => 6, 'percent' => 30]]]]);

        $this->get(route('shop.home', 'demo-hair'))->assertOk()
            ->assertSee('노트닷 헤어')
            ->assertSee('· 온라인 예약')
            ->assertSee('aria-label="예약 단계"', false)
            ->assertSee('@click="next()"', false)
            ->assertSee(':disabled="!canNext"', false)
            ->assertSee('예약 48시간 전까지 취소: 보증금 100% 환불')
            ->assertSee('예약 6시간 전까지 취소: 보증금 30% 환불')
            ->assertDontSee('season-card', false);
    }

    public function test_labels_follow_shop_terms(): void
    {
        // 필라테스: 시술·디자이너가 아니라 수업·강사
        $this->get(route('shop.home', 'demo-pilates'))->assertOk()
            ->assertSee('<dt class="w-14 shrink-0 text-sub">수업</dt>', false)
            ->assertSee('<dt class="w-14 shrink-0 text-sub">강사</dt>', false)
            ->assertDontSee('디자이너');
    }
}
