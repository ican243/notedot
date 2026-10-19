{{-- 공개 안내 화면 틀 (데모 첫 화면). 업무 화면과 같은 차분한 모양(plain). 본문 최대 너비 1100px --}}
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'notedot') · 예약·고객관리</title>
    <meta name="description" content="업종에 묶이지 않는 범용 예약·고객관리 서비스 데모">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="plain min-h-screen font-sans text-base antialiased">
    <header class="border-b border-line bg-card">
        <div class="mx-auto flex w-full max-w-[1148px] flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-bold">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-text text-white"><x-icon name="calendar" :size="18" /></span>
                <span class="text-lg">notedot</span>
            </a>
            <span class="rounded-full border border-line px-2.5 py-0.5 text-sm font-medium text-sub">공개 데모</span>
            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('admin.login') }}" class="btn-line min-h-11">관리자 로그인</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[1148px] px-4 pb-16 sm:px-6">
        @yield('content')
    </main>

    <footer class="border-t border-line bg-card">
        <div class="mx-auto w-full max-w-[1148px] px-4 py-6 text-sm leading-relaxed text-sub sm:px-6">
            @yield('footer')
        </div>
    </footer>
</body>
</html>
