<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Support\Collection;

// 고객 찾기·등록
class CustomerService
{
    // 전화번호는 숫자만 저장 (010-1234-5678 → 01012345678)
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    // 예약 폼에서 고른 고객, 또는 새 고객(이름+전화번호)을 찾거나 만든다. 같은 번호면 기존 고객
    public function resolve(Shop $shop, ?int $customerId, ?string $name, ?string $phone): Customer
    {
        if ($customerId) {
            return $shop->customers()->findOrFail($customerId);
        }

        return $shop->customers()->firstOrCreate(
            ['phone' => self::normalizePhone($phone)],
            ['name' => $name],
        );
    }

    // 이름 앞부분 또는 전화번호 일부로 검색 (최대 10명)
    public function search(Shop $shop, string $q): Collection
    {
        $digits = self::normalizePhone($q);

        return $shop->customers()
            ->when(
                strlen($digits) >= 3,
                fn ($query) => $query->where('phone', 'like', "%{$digits}%"),
                fn ($query) => $query->where('name', 'like', "{$q}%"),
            )
            ->orderBy('name')
            ->limit(10)
            ->get();
    }
}
