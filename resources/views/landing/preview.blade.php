{{-- 첫 화면 캘린더 미리보기 (실제 캘린더와 같은 블록 모양: 연한 담당 색 + 왼쪽 색 줄 + 상태 배지). 넓은 화면은 소개 옆, 좁은 화면은 업종 카드 아래 --}}
<div class="overflow-hidden rounded-lg border border-line bg-card" role="img" aria-label="관리자 예약 캘린더 미리보기">
    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
        <p class="text-sm font-semibold">예약 캘린더 <span class="font-normal text-sub">· 일간 미리보기</span></p>
        <span class="flex items-center gap-1 text-sm text-sub"><x-icon name="bolt" :size="14" /> 실시간</span>
    </div>
    @if ($preview['resources'])
        @php($cols = count($preview['resources']))
        @php($blocks = [[0, 0, 1, 0, 'confirmed'], [0, 1.5, 1.5, 1, 'pending'], [1, 0.5, 2, 2, 'confirmed'], [1, 3, 1, 1, 'confirmed'], [2, 0, 1, 3, 'completed'], [2, 2, 1, 0, 'confirmed']])
        <div class="grid border-b border-line pl-12 text-[13px] font-semibold" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr))">
            @foreach ($preview['resources'] as $r)
                <p class="flex min-w-0 items-center justify-center gap-1.5 px-2 py-2">
                    <span class="cal-dot" style="background: {{ $r->color }}"></span>
                    <span class="truncate">{{ $r->name }}</span>
                </p>
            @endforeach
        </div>
        <div class="flex h-64">
            <div class="w-12 shrink-0 text-xs text-sub">
                @foreach (['10:00', '11:00', '12:00', '13:00'] as $t)
                    <p class="h-1/4 pt-1 pl-2.5">{{ $t }}</p>
                @endforeach
            </div>
            <div class="grid flex-1" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr))">
                @foreach ($preview['resources'] as $i => $r)
                    <div class="relative border-l border-line" style="background: repeating-linear-gradient(180deg, transparent 0 calc(25% - 1px), var(--line) calc(25% - 1px) 25%)">
                        @foreach ($blocks as [$col, $start, $len, $svc, $status])
                            @continue($col !== $i || ! isset($preview['services'][$svc]))
                            <div class="absolute inset-x-1 overflow-hidden rounded px-2 py-1.5 leading-snug text-text
                                        {{ $status === 'pending' ? 'outline-[1.5px] -outline-offset-[1.5px] outline-dashed outline-st-pending' : '' }}"
                                 style="top: calc({{ $start * 25 }}% + 1px); height: calc({{ $len * 25 }}% - 2px); background: color-mix(in srgb, {{ $r->color }} 13%, #fff); border-left: 4px solid {{ $r->color }}">
                                <p class="on-tint flex items-center justify-between gap-1 text-xs">
                                    <span>{{ sprintf('%d:%02d', 10 + floor($start), fmod($start, 1) * 60) }}</span>
                                    @if ($status !== 'confirmed')
                                        <span class="ev-badge ev-badge-{{ $status }} text-xs!">{{ \App\Models\Reservation::STATUSES[$status] }}</span>
                                    @endif
                                </p>
                                <p class="truncate text-[13px] font-semibold {{ $status === 'completed' ? 'on-tint' : '' }}">{{ $preview['services'][$svc] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-4 py-2.5 text-[13px] text-sub">
        <span>왼쪽 색 줄 = 담당</span>
        <span class="flex items-center gap-1"><span class="ev-badge ev-badge-pending text-xs!">대기</span> 결제 대기</span>
        <span class="flex items-center gap-1"><span class="ev-badge ev-badge-completed text-xs!">완료</span></span>
        <span>배지 없음 = 확정</span>
    </div>
</div>
