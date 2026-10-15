{{-- 화면 제목 줄: 제목 + 오른쪽 버튼(slot) --}}
@props(['title', 'sub' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">{{ $title }}</h1>
        @if ($sub)
            <p class="mt-1 text-sm text-sub">{{ $sub }}</p>
        @endif
    </div>
    <div class="flex gap-2">{{ $slot }}</div>
</div>
