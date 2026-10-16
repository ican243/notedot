<?php

namespace App\Services\Realtime;

use App\Contracts\RealtimePublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Node 실시간 서버로 HTTP POST (X-Realtime-Secret 헤더). 실패해도 예약 저장은 이미 끝났으므로 기록만 남긴다
class NodePublisher implements RealtimePublisher
{
    public function __construct(private string $url, private string $secret) {}

    public function publish(int $shopId, string $audience, string $event, array $data = []): void
    {
        try {
            Http::withHeaders(['X-Realtime-Secret' => $this->secret])
                ->connectTimeout(1)
                ->timeout(2)
                ->post(rtrim($this->url, '/').'/publish', [
                    'shop_id' => $shopId,
                    'audience' => $audience,
                    'event' => $event,
                    'data' => (object) $data,
                ])
                ->throw();
        } catch (Throwable $e) {
            // 화면은 재접속·폴링으로 결국 최신 상태를 받으므로 여기서 멈추지 않는다
            Log::warning('realtime publish failed', ['event' => $event, 'shop_id' => $shopId, 'error' => $e->getMessage()]);
        }
    }
}
