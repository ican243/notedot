<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AdminLoginService;
use App\Support\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// 관리자 로그인 / 로그아웃
class LoginController extends Controller
{
    public function show(): View
    {
        return view('admin.auth.login', ['season' => Season::resolve()]);
    }

    public function login(Request $request, AdminLoginService $service): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $service->login($data['email'], $data['password'], $request->boolean('remember'), $request->ip());

        // 세션 고정 공격 방지: 로그인 직후 세션 ID 새로 발급
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
