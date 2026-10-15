<?php

use App\Models\Shop;
use App\Support\CurrentShop;

// 업종 용어 헬퍼: term('resource') → "디자이너" / "강사" / "공간" ...
// 순서: 매장이 직접 바꾼 labels → 업종 프리셋 → 공통 기본값
if (! function_exists('term')) {
    function term(string $key, ?Shop $shop = null): string
    {
        $shop ??= CurrentShop::get();

        return $shop ? $shop->term($key) : (Shop::FALLBACK_LABELS[$key] ?? $key);
    }
}
