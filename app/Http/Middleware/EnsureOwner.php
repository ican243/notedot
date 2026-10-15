<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 대표(owner)만 통과하는 검문소 — 매장 설정, 직원·상품 관리
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('admin')?->isOwner(), 403, '대표 계정만 사용할 수 있습니다.');

        return $next($request);
    }
}
