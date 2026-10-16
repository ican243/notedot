{{-- 데모 모드에서만 보이는 계절 전환. 고른 값은 이 브라우저에만 저장된다 --}}
@props(['fallback'])
@if (config('demo.enabled'))
    <label {{ $attributes->merge(['class' => 'flex items-center gap-1 text-xs text-sub']) }} x-data="seasonSwitch(@js($fallback))">
        <span>계절</span>
        <select x-model="value" @change="apply()" class="rounded-lg border border-line bg-card px-2 py-1 text-xs text-text" aria-label="계절 테마 바꾸기">
            <option value="auto">자동</option>
            <option value="spring">봄</option>
            <option value="summer">여름</option>
            <option value="autumn">가을</option>
            <option value="winter">겨울</option>
        </select>
    </label>
@endif
