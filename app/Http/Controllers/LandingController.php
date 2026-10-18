<?php

namespace App\Http\Controllers;

use App\Contracts\MessageSender;
use App\Contracts\PaymentGateway;
use App\Models\Service;
use App\Models\Shop;
use App\Services\Messaging\LogSender;
use App\Services\Payment\DemoGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

// 첫 주소(/notedot/): 데모 모드면 데모 안내 화면(매장 3곳 예약 체험 + 역할별 관리자 계정), 아니면 관리자 로그인으로
class LandingController extends Controller
{
    private const DEMO_SLUGS = ['demo-hair', 'demo-pilates', 'demo-studyroom'];

    public function __invoke(): View|RedirectResponse
    {
        if (! config('demo.enabled')) {
            return redirect()->route('admin.login');
        }

        $shops = Shop::whereIn('slug', self::DEMO_SLUGS)
            ->withCount(['services' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->sortBy(fn (Shop $s) => array_search($s->slug, self::DEMO_SLUGS))
            ->values();

        return view('landing', [
            'shops' => $shops->map(fn (Shop $s) => $this->card($s)),
            'accounts' => $this->accounts($shops),
            'preview' => $this->preview($shops->first()),
            // 안내 띠: 지금 실제로 연결된 결제·문자 방식 그대로 알린다 (키를 넣어 바뀌면 문구도 바뀜)
            'demoPayment' => app(PaymentGateway::class) instanceof DemoGateway,
            'logMessages' => app(MessageSender::class) instanceof LogSender,
        ]);
    }

    // 업종 카드 한 장: 프리셋(이름·아이콘·색) + 매장 용어 + 예약 방식 + 예약 순서
    private function card(Shop $shop): array
    {
        $preset = config("industries.{$shop->industry}", []);
        $active = $shop->services()->reorder()->where('is_active', true);
        $types = (clone $active)->distinct()->pluck('booking_type');

        return [
            'shop' => $shop,
            'industry' => $preset['name'] ?? $shop->industry,
            'icon' => $preset['icon'] ?? 'calendar',
            'accent' => $preset['accent'] ?? '#3f4754',
            'terms' => $shop->term('resource').' · '.$shop->term('service').' · '.$shop->term('customer'),
            'types' => $types->map(fn ($t) => Service::BOOKING_TYPES[$t] ?? $t)->all(),
            'steps' => [
                $shop->term('service'),
                $shop->term('resource').($types->contains('group') ? ' 또는 시간표' : ''),
                '날짜·시간',
                '정보 입력',
            ],
            'deposit' => (clone $active)->where('deposit', '>', 0)->exists(),
        ];
    }

    // 데모 계정을 역할(대표/직원)별로 묶고, 계정마다 매장 이름·용어를 붙인다
    private function accounts(Collection $shops): array
    {
        $bySlug = $shops->keyBy('slug');

        return collect(config('demo.accounts'))
            ->map(function (array $a) use ($bySlug) {
                $shop = $bySlug->get($a['shop'] ?? null);

                return $a + [
                    'shop_name' => $shop?->name,
                    'industry' => $shop ? config("industries.{$shop->industry}.name") : null,
                    'resource' => $shop?->term('resource') ?? '담당',
                    'customer' => $shop?->term('customer') ?? '고객',
                ];
            })
            ->groupBy(fn (array $a) => $a['role'] ?? 'owner')
            ->all();
    }

    // 첫 화면 캘린더 미리보기: 첫 데모 매장의 실제 담당(이름·색)과 상품 이름만 쓴다 (예약·고객 정보는 쓰지 않음)
    private function preview(?Shop $shop): array
    {
        if (! $shop) {
            return ['resources' => [], 'services' => [], 'resource' => '', 'customer' => ''];
        }

        return [
            'resources' => $shop->resources()->limit(3)->get(['name', 'color'])->all(),
            'services' => $shop->services()->where('is_active', true)->limit(4)->pluck('name')->all(),
            'resource' => $shop->term('resource'),
            'customer' => $shop->term('customer'),
        ];
    }
}
