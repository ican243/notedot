<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PassTransaction;
use App\Models\PointTransaction;
use App\Models\Reservation;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// 포인트: 예약 완료 시 적립(결제액 × 매장 적립률), 완료가 풀리면 회수, 직접 조정. 잔액은 customers.points
class PointService
{
    /**
     * 예약 하나에 "적립돼 있어야 할 포인트"와 "지금까지 적립한 포인트"를 맞춘다
     * 상태가 바뀌거나 회원권으로 처리할 때마다 부른다 (몇 번 불러도 결과가 같다)
     */
    public function sync(Reservation $reservation, ?Staff $by = null): void
    {
        DB::transaction(function () use ($reservation, $by) {
            $customer = $this->lockCustomer($reservation->customer_id);
            $earned = (int) PointTransaction::where('reservation_id', $reservation->id)->whereIn('type', ['earn', 'revoke'])->sum('amount');
            $diff = $this->targetFor($reservation) - $earned;

            // 이미 써 버린 포인트까지 회수해서 마이너스가 되지는 않게
            if ($diff < 0) {
                $diff = -min(-$diff, $customer->points);
            }
            if ($diff === 0) {
                return;
            }

            $this->write($customer, $diff > 0 ? 'earn' : 'revoke', $diff, $by, $reservation->id,
                $reservation->start_at->format('n/j').' 예약 '.($diff > 0 ? '완료' : '완료 취소'));
        });
    }

    // 적립해야 할 포인트: 완료된 예약 + 적립률 > 0 + 회원권으로 처리하지 않은 예약 (권은 이미 미리 낸 돈이므로)
    public function targetFor(Reservation $reservation): int
    {
        $rate = $reservation->shop->point_rate;
        if ($reservation->status !== 'completed' || $rate <= 0) {
            return 0;
        }

        $paidByPass = PassTransaction::where('reservation_id', $reservation->id)->whereIn('type', ['use', 'restore'])->sum('amount') < 0;

        return $paidByPass ? 0 : intdiv($reservation->total_price * $rate, 100);
    }

    // 대표가 직접 더하거나 빼기 (사유 필수)
    public function adjust(Customer $customer, ?Staff $by, int $amount, string $memo): PointTransaction
    {
        return DB::transaction(function () use ($customer, $by, $amount, $memo) {
            $customer = $this->lockCustomer($customer->id);
            if ($amount === 0) {
                throw ValidationException::withMessages(['amount' => '0 이 아닌 값을 입력하세요.']);
            }
            if ($customer->points + $amount < 0) {
                throw ValidationException::withMessages(['amount' => '포인트가 부족합니다. (보유 '.number_format($customer->points).'P)']);
            }

            return $this->write($customer, 'adjust', $amount, $by, null, $memo);
        });
    }

    private function write(Customer $customer, string $type, int $amount, ?Staff $by, ?int $reservationId, ?string $memo): PointTransaction
    {
        $customer->points += $amount;
        $customer->save();

        return PointTransaction::create([
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
            'reservation_id' => $reservationId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $customer->points,
            'memo' => $memo,
            'created_by' => $by?->id,
        ]);
    }

    private function lockCustomer(int $id): Customer
    {
        return Customer::whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
