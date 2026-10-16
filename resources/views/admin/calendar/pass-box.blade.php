{{-- 회원권 처리 칸: 이미 쓴 권(사용 취소) / 쓸 수 있는 권 고르기(회원권으로 처리). $src = 예약 데이터가 담긴 Alpine 변수 이름 --}}
<div x-data="{ pick: '' }" class="flex flex-wrap items-center gap-2 text-sm">
    <template x-for="u in {{ $src }}.passes.used" :key="u.name">
        <span class="badge" x-text="u.name + ' ' + u.amount + ' 차감 · 남은 ' + u.left"></span>
    </template>
    <button type="button" class="btn-line px-2 py-1 text-xs" x-show="{{ $src }}.passes.used.length" @click="restorePass({{ $src }}.id)">사용 취소</button>
    <template x-if="{{ $src }}.passes.usable.length">
        <div class="flex flex-wrap items-center gap-2">
            <select x-model="pick" class="input w-auto py-1 text-sm" aria-label="회원권 선택">
                <option value="">회원권 선택</option>
                <template x-for="p in {{ $src }}.passes.usable" :key="p.id">
                    <option :value="p.id" :disabled="!p.enough" x-text="p.label + ' → ' + p.need + (p.enough ? ' 차감' : ' 필요 (부족)')"></option>
                </template>
            </select>
            <button type="button" class="btn-line px-2 py-1 text-xs" :disabled="!pick || saving" @click="usePass({{ $src }}.id, pick)">회원권으로 처리</button>
        </div>
    </template>
</div>
