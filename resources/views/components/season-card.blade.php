{{-- 풍경 카드: 계절 색 변수만 바뀌고 마크업은 같다. 안에 넣은 내용($slot)만 바꿔 재사용 --}}
<div {{ $attributes->merge(['class' => 'season-card']) }}>
    <span class="sc-sun"></span>
    <span class="sc-sea"></span>
    <span class="sc-hill sc-h1"></span>
    <span class="sc-hill sc-h2"></span>
    <span class="sc-hill sc-h3"></span>
    <span class="sc-hill sc-h4"></span>
    @foreach ([[12, 20], [28, 55], [46, 30], [63, 62], [80, 40], [90, 18]] as [$x, $y])
        <span class="sc-dot" style="left: {{ $x }}%; top: {{ $y }}%"></span>
    @endforeach

    <div class="relative p-6 sm:p-8">
        {{ $slot }}
    </div>
</div>
