{{-- 고객 화면 틀 (모바일 우선, 웜 미니멀). 너비: 휴대폰 꽉 / 768px 이상 600px / 1024px 이상 768px --}}
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', $shop->name) · notedot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="plain warm min-h-screen font-sans antialiased">
    <main class="mx-auto w-full px-4 pt-4 pb-10 md:max-w-[632px] lg:max-w-[800px]">
        @yield('content')
    </main>
</body>
</html>
