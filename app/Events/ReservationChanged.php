<?php

namespace App\Events;

use App\Models\Reservation;
use App\Models\Staff;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

// 예약이 생기거나 바뀜 (created | updated | moved | status). 트랜잭션이 성공(commit)한 뒤에만 나간다
class ReservationChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Reservation $reservation,
        public string $action,
        public ?Staff $by = null,
    ) {}
}
