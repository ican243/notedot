<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShopSettingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 매장 설정 (이름, 업종, 용어, 예약 단위, 영업시간, 테마)
class ShopSettingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.settings.shop', [
            'shop' => $request->user('admin')->shop,
            'industries' => config('industries'),
        ]);
    }

    public function update(ShopSettingRequest $request): RedirectResponse
    {
        $request->user('admin')->shop->update($request->toShopData());

        return back()->with('status', '매장 설정을 저장했습니다.');
    }
}
