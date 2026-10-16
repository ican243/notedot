<?php

namespace App\Services\Realtime;

use App\Contracts\RealtimePublisher;

// Node 서버 없이 운영할 때: 보내지 않는다. 화면이 poll_seconds 마다 다시 불러온다
class PollingPublisher implements RealtimePublisher
{
    public function publish(int $shopId, string $audience, string $event, array $data = []): void {}
}
