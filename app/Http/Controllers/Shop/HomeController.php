<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Shop;
use App\Services\OnlineBookingService;
use Illuminate\View\View;

// 고객 예약 페이지 (상품 → 담당/공간/일정 → 날짜·시간 → 정보 입력). 화면에 필요한 목록을 한 번에 넘긴다
class HomeController extends Controller
{
    public function index(Shop $shop, OnlineBookingService $booking): View
    {
        // 예약 받는 상품 중, 담당하는 resource 가 하나라도 있는 것만
        $services = $shop->services()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Service $s) => ['service' => $s, 'resources' => $booking->resourcesFor($shop, $s)])
            ->filter(fn ($row) => $row['resources']->isNotEmpty());

        $resources = $services->flatMap(fn ($row) => $row['resources'])->unique('id')->values();

        $config = [
            'urls' => [
                'slots' => route('shop.booking.slots', $shop),
                'sessions' => route('shop.booking.sessions', $shop),
                'store' => route('shop.booking.store', $shop),
            ],
            'services' => $services->map(fn ($row) => [
                'id' => $row['service']->id,
                'category' => $row['service']->category,
                'name' => $row['service']->name,
                'description' => $row['service']->description,
                'type' => $row['service']->booking_type,
                'duration' => $row['service']->duration_min,
                'price' => $row['service']->price,
                'deposit' => $row['service']->deposit,
                'resources' => $row['resources']->pluck('id'),
            ])->values(),
            'resources' => $resources->map(fn (Resource $r) => [
                'id' => $r->id, 'name' => $r->name, 'type' => $r->type, 'color' => $r->color, 'maxParty' => $r->max_party,
            ]),
            'labels' => ['resource' => term('resource'), 'service' => term('service'), 'customer' => term('customer')],
        ];

        return view('shop.home', ['shop' => $shop, 'config' => $config]);
    }
}
