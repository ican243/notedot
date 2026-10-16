<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// 첫 주소(/notedot/): 데모 모드면 데모 안내 화면(매장 3곳 예약해 보기 + 관리자 계정), 아니면 관리자 로그인으로
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
            ->map(fn (Shop $s) => [
                'shop' => $s,
                'industry' => config("industries.{$s->industry}.name"),
                'terms' => $s->term('resource').' · '.$s->term('service').' · '.$s->term('customer'),
                'types' => $s->services()->reorder()->where('is_active', true)->distinct()->pluck('booking_type')
                    ->map(fn ($t) => Service::BOOKING_TYPES[$t] ?? $t)->implode(' · '),
            ]);

        return view('landing', ['shops' => $shops]);
    }
}
