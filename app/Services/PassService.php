<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Pass;
use App\Models\PassTransaction;
use App\Models\Reservation;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// 회원권·정액권: 발급, 예약에 사용(차감), 사용 취소(되돌리기), 직접 조정, 권 취소
// 잔액을 바꿀 때는 항상 권 행을 잠그고(lockForUpdate) 거래 한 줄을 쌓는다 → 동시에 두 번 눌러도 잔액이 마이너스가 되지 않는다
// 잠금 순서: 예약 → 권 → 고객(포인트)
class PassService
{
    public function __construct(private PointService $points) {}

    /**
     * @param  array{type:string,name:string,price:int,total:int,service_id?:?int,expires_on?:?string,memo?:?string}  $data
     */
    public function issue(Customer $customer, ?Staff $by, array $data): Pass
    {
        return DB::transaction(function () use ($customer, $by, $data) {
            $pass = $customer->passes()->create([
                'shop_id' => $customer->shop_id,
                'type' => $data['type'],
                'name' => $data['name'],
                'price' => $data['price'],
                'total' => $data['total'],
                'balance' => $data['total'],
                // 상품 전용은 횟수형만 (금액형은 어디에나 쓰는 돈)
                'service_id' => $data['type'] === 'count' ? ($data['service_id'] ?? null) : null,
                'expires_on' => $data['expires_on'] ?? null,
                'memo' => $data['memo'] ?? null,
                'created_by' => $by?->id,
            ]);
            $this->record($pass, 'issue', $pass->total, $by, null, $pass->memo);

            return $pass;
        });
    }

    // 예약을 이 권으로 처리: 횟수형은 1회(그룹은 인원수만큼), 금액형은 예약 금액만큼 차감
    public function useFor(Reservation $reservation, Pass $pass, ?Staff $by): PassTransaction
    {
        return DB::transaction(function () use ($reservation, $pass, $by) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $pass = $this->lock($pass);

            if ($pass->customer_id !== $reservation->customer_id) {
                $this->fail('이 고객의 권이 아닙니다.');
            }
            if ($reservation->status === 'canceled') {
                $this->fail('취소된 예약입니다.');
            }
            if ($this->usedOn($reservation)->isNotEmpty()) {
                $this->fail('이미 회원권으로 처리한 예약입니다.');
            }
            $this->assertUsable($pass);
            if ($pass->service_id && ! $reservation->items()->where('service_id', $pass->service_id)->exists()) {
                $this->fail("{$pass->service->name} 전용 권입니다.");
            }

            $need = $this->needFor($pass, $reservation);
            if ($need <= 0) {
                $this->fail('차감할 금액이 없습니다.');
            }
            if ($pass->balance < $need) {
                $this->fail('잔액이 부족합니다. (남은 '.$pass->format($pass->balance).', 필요 '.$pass->format($need).')');
            }

            $pass->decrement('balance', $need);
            $tx = $this->record($pass, 'use', -$need, $by, $reservation->id, $reservation->start_at->format('n/j H:i').' 예약');
            $this->points->sync($reservation, $by);   // 권으로 낸 예약은 포인트 적립 안 함

            return $tx;
        });
    }

    // 이 예약에 쓴 권을 모두 되돌린다. 되돌릴 것이 없으면 false
    // skipCanceled: 예약 취소에 따라 자동으로 되돌릴 때는 이미 취소된 권은 건너뛴다 (예약 취소 자체는 막지 않음)
    public function restoreFor(Reservation $reservation, ?Staff $by, string $memo = '사용 취소', bool $skipCanceled = false): bool
    {
        return DB::transaction(function () use ($reservation, $by, $memo, $skipCanceled) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $used = $this->usedOn($reservation);

            foreach ($used as $passId => $amount) {
                $pass = $this->lock(Pass::withTrashed()->findOrFail($passId));
                if ($pass->isCanceled()) {
                    if ($skipCanceled) {
                        continue;
                    }
                    $this->fail('취소된 권에는 되돌릴 수 없습니다.');
                }
                $pass->increment('balance', $amount);
                $this->record($pass, 'restore', $amount, $by, $reservation->id, $memo);
            }

            if ($used->isNotEmpty()) {
                $this->points->sync($reservation, $by);
            }

            return $used->isNotEmpty();
        });
    }

    // 대표가 잔액을 직접 더하거나 빼기 (사유 필수)
    public function adjust(Pass $pass, ?Staff $by, int $amount, string $memo): PassTransaction
    {
        return DB::transaction(function () use ($pass, $by, $amount, $memo) {
            $pass = $this->lock($pass);
            if ($pass->isCanceled()) {
                $this->fail('취소된 권입니다.', 'amount');
            }
            if ($amount === 0) {
                $this->fail('0 이 아닌 값을 입력하세요.', 'amount');
            }
            if ($pass->balance + $amount < 0) {
                $this->fail('잔액보다 많이 뺄 수 없습니다. (남은 '.$pass->format($pass->balance).')', 'amount');
            }

            $pass->increment('balance', $amount);

            return $this->record($pass, 'adjust', $amount, $by, null, $memo);
        });
    }

    // 권 취소(환불 등): 남은 잔액을 0 으로. 돈을 돌려주는 일은 결제 단계(Phase 7)에서
    public function cancel(Pass $pass, ?Staff $by, ?string $memo = null): Pass
    {
        return DB::transaction(function () use ($pass, $by, $memo) {
            $pass = $this->lock($pass);
            if ($pass->isCanceled()) {
                return $pass;
            }

            $left = $pass->balance;
            $pass->update(['balance' => 0, 'canceled_at' => now()]);
            $this->record($pass, 'cancel', -$left, $by, null, $memo);

            return $pass;
        });
    }

    // 이 예약에 쓸 수 있는 고객의 권 (상품 전용 권은 그 상품이 들어간 예약만)
    public function usableFor(Reservation $reservation): Collection
    {
        $serviceIds = $reservation->items()->pluck('service_id');

        return Pass::where('customer_id', $reservation->customer_id)
            ->usable()
            ->where(fn ($q) => $q->whereNull('service_id')->orWhereIn('service_id', $serviceIds))
            ->orderByRaw('expires_on is null, expires_on')   // 만료가 가까운 권부터
            ->get();
    }

    // 예약 창에 보여 줄 회원권 정보: 이미 쓴 권 / 지금 쓸 수 있는 권(각각 얼마 빠지는지)
    public function summaryFor(Reservation $reservation): array
    {
        $used = $this->usedOn($reservation);
        $usedPasses = Pass::withTrashed()->whereIn('id', $used->keys())->get();

        return [
            'used' => $usedPasses->map(fn (Pass $p) => ['name' => $p->name, 'amount' => $p->format($used[$p->id]), 'left' => $p->format($p->balance)])->values(),
            'usable' => $used->isNotEmpty() || $reservation->status === 'canceled' ? [] : $this->usableFor($reservation)
                ->map(fn (Pass $p) => [
                    'id' => $p->id,
                    'label' => $p->name.' · 남은 '.$p->format($p->balance).($p->expires_on ? ' (~'.$p->expires_on->format('n/j').')' : ''),
                    'need' => $p->format($this->needFor($p, $reservation)),
                    'enough' => $p->balance >= $this->needFor($p, $reservation),
                ])->values(),
        ];
    }

    /** @return Collection<int, int> [pass_id => 아직 되돌리지 않은 사용량] */
    public function usedOn(Reservation $reservation): Collection
    {
        return PassTransaction::where('reservation_id', $reservation->id)
            ->whereIn('type', ['use', 'restore'])
            ->selectRaw('pass_id, -sum(amount) as used')
            ->groupBy('pass_id')
            ->pluck('used', 'pass_id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0);
    }

    public function needFor(Pass $pass, Reservation $reservation): int
    {
        if ($pass->type === 'count') {
            return $reservation->class_session_id ? $reservation->party_size : 1;
        }

        return $reservation->total_price;
    }

    private function assertUsable(Pass $pass): void
    {
        if ($pass->isCanceled()) {
            $this->fail('취소된 권입니다.');
        }
        if ($pass->isExpired()) {
            $this->fail('만료된 권입니다. ('.$pass->expires_on->format('Y-m-d').' 까지)');
        }
    }

    private function record(Pass $pass, string $type, int $amount, ?Staff $by, ?int $reservationId, ?string $memo): PassTransaction
    {
        return $pass->transactions()->create([
            'shop_id' => $pass->shop_id,
            'reservation_id' => $reservationId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $pass->balance,
            'memo' => $memo,
            'created_by' => $by?->id,
        ]);
    }

    private function lock(Pass $pass): Pass
    {
        return Pass::withTrashed()->whereKey($pass->id)->lockForUpdate()->firstOrFail();
    }

    private function fail(string $message, string $field = 'pass_id'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
