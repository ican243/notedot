<?php

namespace App\Services\Auth;

use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// 관리자 로그인: 실패 횟수 제한 + 비밀번호 확인 + admin 가드에 로그인
class AdminLoginService
{
    private const MAX_ATTEMPTS = 5;      // 1분에 5번까지

    private const DECAY_SECONDS = 60;

    public function login(string $email, string $password, bool $remember, string $ip): Staff
    {
        $key = 'admin-login:'.Str::lower($email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "로그인 시도가 너무 많습니다. {$seconds}초 후 다시 시도하세요.",
            ]);
        }

        $staff = $this->findByCredentials($email, $password);

        if (! $staff) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            throw ValidationException::withMessages([
                'email' => '이메일 또는 비밀번호가 올바르지 않습니다.',
            ]);
        }

        RateLimiter::clear($key);
        Auth::guard('admin')->login($staff, $remember);
        $staff->forceFill(['last_login_at' => now()])->save();

        return $staff;
    }

    // 이메일은 매장별로만 유일하므로, 같은 이메일 계정 중 비밀번호가 맞는 계정을 찾는다
    private function findByCredentials(string $email, string $password): ?Staff
    {
        return Staff::where('email', $email)
            ->get()
            ->first(fn (Staff $s) => Hash::check($password, $s->password));
    }
}
