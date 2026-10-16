<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\Staff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

// 고객 찾기·등록, 고객 관리(CRM) 목록·상세에 쓰는 조회
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

    // 직원이 볼 수 있는 고객: 대표는 전부, 직원은 자기 resource 에 예약한 적이 있는 고객만
    public function visibleTo(Staff $staff): HasMany
    {
        return $staff->shop->customers()->when(! $staff->isOwner(), fn (Builder $q) => $q->whereHas(
            'reservations', fn (Builder $r) => $r->whereIn('resource_id', $staff->visibleResourceIds()),
        ));
    }

    public function canView(Staff $staff, Customer $customer): bool
    {
        return $this->visibleTo($staff)->whereKey($customer->id)->exists();
    }

    // 고객 목록: 이름·번호 검색, 방문 수·최근 방문·쓸 수 있는 권 개수를 함께
    public function list(Staff $staff, ?string $q, string $sort): LengthAwarePaginator
    {
        $digits = self::normalizePhone((string) $q);

        return $this->visibleTo($staff)
            ->when(filled($q), fn ($query) => strlen($digits) >= 3
                ? $query->where('phone', 'like', "%{$digits}%")
                : $query->where('name', 'like', "%{$q}%"))
            ->withCount(['reservations as visits' => fn ($r) => $r->where('status', 'completed')])
            ->withMax(['reservations as last_visit' => fn ($r) => $r->where('status', 'completed')], 'start_at')
            ->withCount(['passes as usable_passes' => fn ($p) => $p->usable()])
            ->when($sort === 'recent', fn ($query) => $query->orderByDesc('last_visit'))
            ->when($sort === 'visits', fn ($query) => $query->orderByDesc('visits'))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    // 상세 화면 숫자: 방문·노쇼·취소 횟수, 마지막 방문, 다음 예약, 총 이용 금액
    public function stats(Customer $customer, array $resourceIds): array
    {
        $base = fn () => $customer->reservations()->whereIn('resource_id', $resourceIds);

        return [
            'visits' => $base()->where('status', 'completed')->count(),
            'no_shows' => $base()->where('status', 'no_show')->count(),
            'cancels' => $base()->where('status', 'canceled')->count(),
            'last_visit' => $base()->where('status', 'completed')->max('start_at'),
            'next' => $base()->whereIn('status', ['pending', 'confirmed'])->where('start_at', '>=', now())->orderBy('start_at')->with('resource')->first(),
            'spent' => (int) $base()->where('status', 'completed')->sum('total_price'),
        ];
    }
}
