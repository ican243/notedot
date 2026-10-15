{{-- 폼 한 칸: 이름표 + 입력칸(slot) + 도움말 + 오류 문구 --}}
@props(['label', 'name' => null, 'help' => null])
<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <label @if ($name) for="{{ $name }}" @endif class="block text-sm font-medium">{{ $label }}</label>
    {{ $slot }}
    @if ($help)
        <p class="text-xs text-sub">{{ $help }}</p>
    @endif
    @if ($name)
        @error($name)
            <p class="text-sm text-st-noshow">{{ $message }}</p>
        @enderror
    @endif
</div>
