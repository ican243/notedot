{{-- 관리자 화면 틀 (PC/태블릿). 각 화면은 @extends('layouts.admin') 후 @section('content') 만 채운다. 업무 화면이라 계절 장식 없이(plain) --}}
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
<body class="plain min-h-screen font-sans antialiased">
@auth('admin')
    @php
        $me = auth('admin')->user();
        // [주소 이름, 화면 이름, 이 주소로 시작하면 선택 표시, 대표 전용]
        $menu = [
            ['admin.dashboard', '대시보드', 'admin.dashboard', false],
            ['admin.calendar', '예약 캘린더', 'admin.calendar', false],
            ['admin.customers.index', term('customer').' 관리', 'admin.customers.*', false],
            ['admin.resources.index', term('resource').' 관리', 'admin.resources.*,admin.schedules.*', true],
            ['admin.services.index', term('service').' 관리', 'admin.services.*', true],
            ['admin.staffs.index', '직원 계정', 'admin.staffs.*', true],
            ['admin.stats', '통계', 'admin.stats', true],
            ['admin.notifications.index', '알림 기록', 'admin.notifications.*', true],
            ['admin.settings.shop', '매장 설정', 'admin.settings.*', true],
        ];
    @endphp
    <div class="min-h-screen md:grid md:grid-cols-[220px_1fr]">
        {{-- 왼쪽 메뉴 --}}
        <aside class="border-b border-line bg-card md:border-b-0 md:border-r">
            <div class="flex items-center justify-between px-5 py-4 md:block">
                <a href="{{ route('admin.dashboard') }}" class="text-lg font-bold">notedot</a>
                <p class="text-sm text-sub md:mt-1">{{ $me->shop->name }}</p>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:flex-col">
                @foreach ($menu as [$route, $label, $pattern, $ownerOnly])
                    @continue($ownerOnly && ! $me->isOwner())
                    @php($active = request()->routeIs(...explode(',', $pattern)))
                    <a href="{{ route($route) }}"
                       @class([
                           'rounded-lg px-3 py-2 text-sm whitespace-nowrap',
                           'bg-bg font-semibold' => $active,
                           'text-sub hover:bg-bg' => ! $active,
                       ])>{{ $label }}</a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-col">
            {{-- 위쪽 바 --}}
            <header class="flex items-center justify-end gap-3 border-b border-line bg-card px-5 py-3">
                @if (config('demo.enabled'))
                    <span class="badge mr-auto">데모 · 매일 {{ config('demo.reset_at') }} 초기화</span>
                @endif
                <span class="text-sm text-sub">{{ $me->name }} · {{ $me->isOwner() ? '대표' : '직원' }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-bg">로그아웃</button>
                </form>
            </header>

            <main class="mx-auto w-full @yield('width', 'max-w-6xl') flex-1 p-5 md:p-8">
                @if (session('status'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                         class="mb-5 rounded-lg border border-line bg-card px-4 py-3 text-sm">
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-5 rounded-lg border border-st-noshow bg-card px-4 py-3 text-sm text-st-noshow">
                        {{ session('error') }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
@else
    @yield('content')
@endauth
</body>
</html>
