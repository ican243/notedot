<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Realtime\RealtimeTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// 관리자 화면이 실시간 서버에 접속하기 직전에 받아 가는 출입증 (1분짜리, 접속할 때마다 새로)
class RealtimeTokenController extends Controller
{
    public function __invoke(Request $request, RealtimeTokenService $tokens): JsonResponse
    {
        abort_unless($tokens->usesNode(), 404);

        return response()->json(['token' => $tokens->forStaff($request->user('admin'))])
            ->header('Cache-Control', 'no-store');
    }
}
