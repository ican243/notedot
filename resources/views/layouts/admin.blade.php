{{-- 관리자 화면 틀 (PC/태블릿). 각 화면은 @extends('layouts.admin') 후 @section('content') 만 채운다 --}}
<!DOCTYPE html>
<html lang="ko" data-season="{{ $season ?? auth('admin')->user()?->shop?->season() ?? \App\Support\Season::resolve() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', '관리자') · notedot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
@auth('admin')
    @php($me = auth('admin')->user())
    <div class="min-h-screen md:grid md:grid-cols-[220px_1fr]">
        {{-- 왼쪽 메뉴 --}}
        <aside class="border-b border-line bg-card md:border-b-0 md:border-r">
            <div class="flex items-center justify-between px-5 py-4 md:block">
                <a href="{{ route('admin.dashboard') }}" class="text-lg font-bold">notedot</a>
                <p class="text-sm text-sub md:mt-1">{{ $me->shop->name }}</p>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:flex-col md:pb-0">
                <a href="{{ route('admin.dashboard') }}"
                   @class([
                       'rounded-lg px-3 py-2 text-sm whitespace-nowrap',
                       'bg-bg font-semibold' => request()->routeIs('admin.dashboard'),
                       'text-sub hover:bg-bg' => ! request()->routeIs('admin.dashboard'),
                   ])>대시보드</a>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-col">
            {{-- 위쪽 바 --}}
            <header class="flex items-center justify-end gap-3 border-b border-line bg-card px-5 py-3">
                <span class="text-sm text-sub">{{ $me->name }} · {{ $me->isOwner() ? '대표' : '직원' }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-bg">로그아웃</button>
                </form>
            </header>

            <main class="mx-auto w-full max-w-6xl flex-1 p-5 md:p-8">
                @yield('content')
            </main>
        </div>
    </div>
@else
    @yield('content')
@endauth
</body>
</html>
