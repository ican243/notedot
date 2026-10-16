<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Realtime\RealtimeTokenService;
use Illuminate\Http\JsonResponse;

// 고객 예약 화면용 출입증. 공개 룸(시간 변경 알림만)에만 들어갈 수 있다
class RealtimeTokenController extends Controller
{
    public function __invoke(Shop $shop, RealtimeTokenService $tokens): JsonResponse
    {
        abort_unless($tokens->usesNode(), 404);

        return response()->json(['token' => $tokens->forPublic($shop)])
            ->header('Cache-Control', 'no-store');
    }
}
