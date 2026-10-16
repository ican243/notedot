{{-- 공개 안내 화면 틀 (데모 첫 화면). 매장이 없는 화면이라 계절은 오늘 날짜 기준 --}}
@php($season = \App\Support\Season::resolve())
<!DOCTYPE html>
<html lang="ko" data-season="{{ $season }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'notedot') · 예약·고객관리</title>
    <meta name="description" content="업종에 묶이지 않는 범용 예약·고객관리 서비스 데모">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @include('partials.season-boot')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    <main class="mx-auto w-full max-w-4xl p-4 sm:p-6">
        <div class="mb-3 flex justify-end"><x-season-switch :fallback="$season" /></div>
        @yield('content')
    </main>
</body>
</html>
