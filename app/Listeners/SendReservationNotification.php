<?php

namespace App\Listeners;

use App\Events\ReservationChanged;
use App\Services\NotificationService;

// 예약이 확정·취소되면 고객에게 알림 (실시간 알림과 같은 사건을 듣는다)
class SendReservationNotification
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(ReservationChanged $event): void
    {
        $this->notifications->forReservation($event->reservation, $event->action);
    }
}
