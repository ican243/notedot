<?php

namespace App\Services\Realtime;

use App\Models\Shop;
use App\Models\Staff;
use Firebase\JWT\JWT;

// 화면이 Node 서버에 접속할 때 내미는 짧은 출입증(JWT)과, 화면에 넘길 실시간 설정
class RealtimeTokenService
{
    // 관리자 화면용: 매장 룸(shop:{id})에 들어간다
    public function forStaff(Staff $staff): string
    {
        return $this->issue($staff->shop_id, 'admin', ['sub' => (string) $staff->id]);
    }

    // 고객 예약 화면용: 공개 룸(shop:{id}:public)에만 들어간다
    public function forPublic(Shop $shop): string
    {
        return $this->issue($shop->id, 'public');
    }

    // 화면(JavaScript)에 넘길 설정
    public function clientConfig(string $tokenUrl): array
    {
        return [
            'driver' => $this->usesNode() ? 'node' : 'poll',
            'path' => config('realtime.socket_path') ?: rtrim((string) parse_url(config('app.url'), PHP_URL_PATH), '/').'/socket.io',
            'tokenUrl' => $tokenUrl,
            'pollSeconds' => config('realtime.poll_seconds'),
        ];
    }

    // Node 방식은 시크릿 두 개가 모두 있어야 켠다 (하나라도 없으면 폴링)
    public function usesNode(): bool
    {
        return config('realtime.driver') === 'node'
            && filled(config('realtime.secret'))
            && filled(config('realtime.jwt_secret'));
    }

    private function issue(int $shopId, string $audience, array $extra = []): string
    {
        $now = time();

        return JWT::encode([
            'iss' => 'notedot',
            'aud' => $audience,
            'shop' => $shopId,
            'iat' => $now,
            'exp' => $now + config('realtime.token_ttl'),
        ] + $extra, config('realtime.jwt_secret'), 'HS256');
    }
}
