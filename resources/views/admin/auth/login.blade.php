@extends('layouts.admin')

@section('title', '로그인')

@section('content')
<div class="flex min-h-screen items-center justify-center p-4">
    <div class="w-full max-w-md">
        <x-season-card class="mb-6">
            <p class="text-sm opacity-80">예약·고객관리</p>
            <h1 class="mt-1 text-3xl font-bold">notedot</h1>
            <p class="mt-10 text-sm opacity-80">관리자 로그인</p>
        </x-season-card>

        <form method="POST" action="{{ route('admin.login.attempt') }}"
              class="space-y-4 rounded-card bg-card p-6 shadow-card">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">이메일</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username"
                       class="w-full rounded-lg border border-line px-3 py-2.5 focus:border-text focus:outline-none">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">비밀번호</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-line px-3 py-2.5 focus:border-text focus:outline-none">
            </div>

            @error('email')
                <p class="text-sm text-st-noshow">{{ $message }}</p>
            @enderror

            <label class="flex items-center gap-2 text-sm text-sub">
                <input type="checkbox" name="remember" value="1"> 로그인 상태 유지
            </label>

            <button class="btn-main w-full">로그인</button>
        </form>
    </div>
</div>
@endsection
