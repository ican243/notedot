<?php

namespace App\Contracts;

// 실시간 알림 보내기. Node 서버로 보내거나(NodePublisher), 아무것도 안 하고 화면 폴링에 맡긴다(PollingPublisher)
interface RealtimePublisher
{
    /**
     * @param  'admin'|'public'  $audience  admin = 관리자 화면, public = 고객 예약 화면 (고객 정보 넣지 않기)
     */
    public function publish(int $shopId, string $audience, string $event, array $data = []): void;
}
