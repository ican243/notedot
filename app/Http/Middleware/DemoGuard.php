<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 데모 모드 검문소: 여러 사람이 같은 데모 계정을 쓰므로, 다른 사람을 못 들어오게 만드는 변경을 막는다
class DemoGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $reason = match (true) {
            $request->routeIs('admin.staffs.destroy') => '데모에서는 직원 계정을 삭제할 수 없습니다.',
            $request->routeIs('admin.staffs.update') && $this->changesLogin($request) => '데모에서는 이메일·비밀번호를 바꿀 수 없습니다.',
            default => null,
        };

        if ($reason === null) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, $reason);

        return back()->with('error', $reason);
    }

    // 로그인 정보(이메일·비밀번호)를 바꾸려는 요청인가
    private function changesLogin(Request $request): bool
    {
        $staff = $request->route('staff');

        return $request->filled('password')
            || ($staff instanceof Staff && $request->input('email') !== $staff->email);
    }
}
