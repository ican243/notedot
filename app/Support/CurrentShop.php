<?php

namespace App\Support;

use App\Models\Shop;

// 지금 요청이 어느 매장 것인지: 고객 주소(/s/{slug})면 주소의 매장, 관리자면 로그인한 직원의 매장
class CurrentShop
{
    public static function get(): ?Shop
    {
        $param = request()->route()?->parameter('shop');

        if ($param instanceof Shop) {
            return $param;
        }

        // 주소 바인딩 순서상 아직 글자(slug)로 남아 있을 수 있다
        if (is_string($param)) {
            return Shop::where('slug', $param)->first();
        }

        return auth('admin')->user()?->shop;
    }
}
