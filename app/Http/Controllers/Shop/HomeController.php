<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\View\View;

// 고객 예약 페이지 첫 화면 (예약 흐름은 Phase 4에서 채움)
class HomeController extends Controller
{
    public function index(Shop $shop): View
    {
        return view('shop.home', ['shop' => $shop]);
    }
}
