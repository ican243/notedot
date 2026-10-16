<?php

namespace App\Listeners;

use App\Contracts\RealtimePublisher;
use App\Events\ClassSessionChanged;
use App\Events\ReservationChanged;

// 예약·일정 변경을 실시간 서버로 전달
// 관리자 화면에는 무엇이 바뀌었는지(담당·시각·상태), 고객 화면에는 "이 날짜 시간이 바뀌었다"만 (이름·전화번호는 보내지 않는다)
class PushToRealtime
{
    public function __construct(private RealtimePublisher $publisher) {}

    public function handleReservation(ReservationChanged $event): void
    {
        $r = $event->reservation;

        $this->publisher->publish($r->shop_id, 'admin', 'reservation.changed', [
            'id' => $r->id,
            'action' => $event->action,
            'status' => $r->status,
            'channel' => $r->channel,
            'resource_id' => $r->resource_id,
            'resource' => $r->resource?->name,
            'start' => $r->start_at->format('Y-m-d H:i'),
            'group' => $r->class_session_id !== null,
            'by' => $event->by?->id,
        ]);

        $this->publisher->publish($r->shop_id, 'public', 'availability.changed', [
            'kind' => $r->class_session_id ? 'session' : 'time',
            'date' => $r->start_at->format('Y-m-d'),
        ]);
    }

    public function handleSession(ClassSessionChanged $event): void
    {
        $s = $event->session;

        $this->publisher->publish($s->shop_id, 'admin', 'session.changed', [
            'id' => $s->id,
            'action' => $event->action,
            'count' => $event->count,
            'resource_id' => $s->resource_id,
            'resource' => $s->resource?->name,
            'start' => $s->start_at->format('Y-m-d H:i'),
            'by' => $event->by?->id,
        ]);

        // 일정은 그 강사의 1:1 시간도 막으므로 둘 다 다시 불러오게 한다
        $this->publisher->publish($s->shop_id, 'public', 'availability.changed', [
            'kind' => 'all',
            'date' => $s->start_at->format('Y-m-d'),
        ]);
    }
}
