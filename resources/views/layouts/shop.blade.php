{{-- 고객 화면 틀 (모바일 우선) --}}
<!DOCTYPE html>
<html lang="ko" data-season="{{ $shop->season() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', $shop->name) · notedot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @include('partials.season-boot')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    <main class="mx-auto w-full max-w-md p-4">
        @if (config('demo.enabled'))
            <div class="mb-3 flex justify-end"><x-season-switch :fallback="$shop->season()" /></div>
        @endif
        @yield('content')
    </main>
</body>
</html>
