@extends('layouts.public')

@section('title', 'notedot 데모')

@php($industryNames = $shops->pluck('industry')->implode(', '))
@php($owners = $accounts['owner'] ?? collect())
@php($staffs = $accounts['staff'] ?? collect())

@section('content')
{{-- 1. 히어로: 왼쪽 소개·바로가기, 오른쪽 관리자 캘린더 미리보기 (실제 캘린더와 같은 블록 모양) --}}
<section class="grid items-center gap-8 py-10 sm:py-14 lg:grid-cols-[1fr_1.05fr] lg:gap-12">
    <div>
        <p class="text-sm font-semibold text-sub">예약·고객관리 SaaS</p>
        <h1 class="mt-3 text-[2rem] leading-tight font-bold tracking-tight sm:text-[2.6rem]">예약부터 고객 관리, 매출까지<br class="hidden sm:inline"> 한 화면에서</h1>
        <p class="mt-5 max-w-[34rem] text-base leading-relaxed text-sub sm:text-[1.0625rem]">
            온라인 예약, 담당자별 캘린더, 보증금 결제, 회원권, 알림, 통계를 하나로 묶었습니다.
            {{ $industryNames }} 매장이 같은 코드로 돌아가고, 업종에 맞는 용어와 예약 방식은 매장 설정으로 바뀝니다.
        </p>
        <div class="mt-7 flex flex-wrap gap-3">
            <a href="#shops" class="btn-main gap-2">손님으로 예약 체험 <x-icon name="arrow" :size="18" /></a>
            <a href="#admin" class="btn-line min-h-11 px-5 text-base">관리자 화면 체험</a>
        </div>
        <p class="mt-4 text-sm text-sub">회원가입 없이 바로 써 볼 수 있습니다{{ $demoPayment && $logMessages ? ' · 실제 결제·발송 없음' : '' }}</p>
    </div>

    <div class="rounded-xl border border-line bg-[#f4f5f7] p-3 sm:p-4">
        <div class="overflow-hidden rounded-lg border border-line bg-card" role="img" aria-label="관리자 예약 캘린더 미리보기">
            <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                <p class="text-sm font-semibold">예약 캘린더 <span class="font-normal text-sub">· 일간 미리보기</span></p>
                <span class="flex items-center gap-1 rounded-md border border-line px-2 py-0.5 text-xs font-medium text-sub">
                    <x-icon name="bolt" :size="12" /> 실시간
                </span>
            </div>
            @if ($preview['resources'])
                @php($cols = count($preview['resources']))
                @php($blocks = [[0, 0, 1, 0, 'confirmed'], [0, 1.5, 1.5, 1, 'pending'], [1, 0.5, 2, 2, 'confirmed'], [1, 3, 1, 1, 'confirmed'], [2, 0, 1, 3, 'completed'], [2, 2, 1, 0, 'confirmed']])
                <div class="grid border-b border-line pl-11 text-xs font-semibold" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr))">
                    @foreach ($preview['resources'] as $r)
                        <p class="flex min-w-0 items-center justify-center gap-1.5 px-2 py-2">
                            <span class="cal-dot" style="background: {{ $r->color }}"></span>
                            <span class="truncate">{{ $r->name }}</span>
                        </p>
                    @endforeach
                </div>
                <div class="relative flex h-60 sm:h-64">
                    <div class="w-11 shrink-0 text-[11px] text-sub">
                        @foreach (['10:00', '11:00', '12:00', '13:00'] as $t)
                            <p class="h-1/4 pt-1 pl-2">{{ $t }}</p>
                        @endforeach
                    </div>
                    <div class="grid flex-1" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr))">
                        @foreach ($preview['resources'] as $i => $r)
                            <div class="relative border-l border-line" style="background: repeating-linear-gradient(180deg, transparent 0 calc(25% - 1px), var(--line) calc(25% - 1px) 25%)">
                                @foreach ($blocks as [$col, $start, $len, $svc, $status])
                                    @continue($col !== $i || ! isset($preview['services'][$svc]))
                                    {{-- 실제 캘린더 블록과 같은 모양: 연한 담당 색 + 왼쪽 색 줄 + 상태 배지 --}}
                                    <div class="absolute inset-x-1 overflow-hidden rounded px-1.5 py-1 text-[11px] leading-snug text-text
                                                {{ $status === 'pending' ? 'outline-[1.5px] -outline-offset-[1.5px] outline-dashed outline-st-pending' : '' }}"
                                         style="top: calc({{ $start * 25 }}% + 1px); height: calc({{ $len * 25 }}% - 2px); background: color-mix(in srgb, {{ $r->color }} 13%, #fff); border-left: 4px solid {{ $r->color }}">
                                        <p class="flex items-center justify-between gap-1 text-sub">
                                            <span>{{ sprintf('%d:%02d', 10 + floor($start), fmod($start, 1) * 60) }}</span>
                                            @if ($status !== 'confirmed')
                                                <span class="ev-badge ev-badge-{{ $status }}">{{ \App\Models\Reservation::STATUSES[$status] }}</span>
                                            @endif
                                        </p>
                                        <p class="truncate font-semibold {{ $status === 'completed' ? 'text-sub' : '' }}">{{ $preview['services'][$svc] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-4 py-2.5 text-xs text-sub">
                <span>왼쪽 색 줄 = 담당</span>
                <span class="flex items-center gap-1"><span class="ev-badge ev-badge-pending">대기</span> 결제 대기</span>
                <span class="flex items-center gap-1"><span class="ev-badge ev-badge-completed">완료</span></span>
                <span>배지 없음 = 확정</span>
            </div>
        </div>
    </div>
</section>

{{-- 2. 안내 띠: 데모에서 일어나지 않는 일 --}}
<section aria-label="데모 이용 안내" class="panel grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
    @foreach (array_filter([
        ['card', $demoPayment ? '실제 결제 없음' : '테스트 결제', $demoPayment ? '보증금은 데모 결제창에서 승인·실패만 체험합니다.' : 'PG 테스트 모드라 실제 돈은 나가지 않습니다.'],
        $logMessages ? ['message', '문자 발송 없음', '예약 확정·리마인드 알림은 발송 기록에만 남습니다.'] : null,
        ['refresh', '매일 '.config('demo.reset_at').' 초기화', '만든 예약·고객·설정은 다음 날 처음 상태로 돌아갑니다.'],
        ['lock', '계정 보호', '비밀번호·이메일 변경과 직원 삭제는 막혀 있습니다.'],
    ]) as [$icon, $title, $desc])
        <div class="flex gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-bg text-text"><x-icon :name="$icon" :size="18" /></span>
            <div>
                <p class="font-semibold">{{ $title }}</p>
                <p class="mt-0.5 text-[0.9375rem] leading-relaxed text-sub">{{ $desc }}</p>
            </div>
        </div>
    @endforeach
</section>

{{-- 3. 업종별 데모 매장 --}}
<section id="shops" class="scroll-mt-6 pt-16">
    <h2 class="text-2xl font-bold tracking-tight">업종별 데모 매장</h2>
    <p class="mt-2 max-w-[40rem] text-base leading-relaxed text-sub">손님 예약 화면은 휴대폰 기준입니다. 예약하면 관리자 캘린더에 바로 나타납니다. 창 두 개를 나란히 열어 보세요.</p>

    <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($shops as $row)
            <article class="panel flex flex-col overflow-hidden">
                <div class="flex flex-1 flex-col p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg" style="color: {{ $row['accent'] }}; background: color-mix(in srgb, {{ $row['accent'] }} 11%, white)">
                            <x-icon :name="$row['icon']" :size="22" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold" style="color: {{ $row['accent'] }}">{{ $row['industry'] }}</p>
                            <h3 class="truncate text-xl font-bold">{{ $row['shop']->name }}</h3>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($row['types'] as $type)
                            <span class="rounded-full border border-line px-2.5 py-0.5 text-sm font-medium">{{ $type }}</span>
                        @endforeach
                        @if ($row['deposit'])
                            <span class="rounded-full border border-line px-2.5 py-0.5 text-sm font-medium">보증금 결제</span>
                        @endif
                    </div>

                    <dl class="mt-5 space-y-2.5 text-[0.9375rem]">
                        <div class="flex gap-3">
                            <dt class="w-16 shrink-0 text-sub">용어</dt>
                            <dd>{{ $row['terms'] }}</dd>
                        </div>
                        <div class="flex gap-3">
                            <dt class="w-16 shrink-0 text-sub">예약 순서</dt>
                            <dd>{{ implode(' → ', $row['steps']) }}</dd>
                        </div>
                        <div class="flex gap-3">
                            <dt class="w-16 shrink-0 text-sub">상품</dt>
                            <dd>{{ $row['shop']->services_count }}개</dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-6">
                        <a href="{{ route('shop.home', $row['shop']) }}" class="btn-main w-full gap-2" aria-label="{{ $row['shop']->name }} 예약 체험하기">
                            예약 체험하기 <x-icon name="arrow" :size="18" />
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>

{{-- 4. 관리자 체험: 역할별 --}}
<section id="admin" class="scroll-mt-6 pt-16">
    <h2 class="text-2xl font-bold tracking-tight">관리자 화면 체험</h2>
    <p class="mt-2 max-w-[40rem] text-base leading-relaxed text-sub">
        같은 매장이라도 역할에 따라 보이는 화면이 다릅니다. 버튼을 누르면 아이디가 채워진 로그인 화면으로 이동합니다.
        비밀번호는 모두 <code class="rounded bg-card px-1.5 py-0.5 font-semibold text-text">{{ config('demo.password') }}</code> 입니다.
    </p>

    <div class="mt-6 grid gap-5 md:grid-cols-2">
        @foreach ([
            ['owner', 'briefcase', '대표 (원장)', '대표로', '매장 전체를 운영하는 계정', $owners, fn ($a) => [
                [true, '전체 '.$a['resource'].' 캘린더 · 예약 등록·변경'],
                [true, $a['customer'].' 관리 · 회원권 · 포인트'],
                [true, '매출 통계 · 알림 발송 기록'],
                [true, '매장·상품·근무표 설정 · 보증금 환불'],
            ]],
            ['staff', 'user', '직원', '직원으로', '현장에서 예약을 받는 계정', $staffs, fn ($a) => [
                [true, '내 캘린더 (본인 담당 '.$a['resource'].'만)'],
                [true, '담당 '.$a['customer'].' 조회 · 메모'],
                [false, '매출 · 통계'],
                [false, '매장 설정 · 회원권 발급 · 환불'],
            ]],
        ] as [$role, $icon, $title, $as, $desc, $list, $abilities])
            @continue($list->isEmpty())
            @php($main = $list->first())
            <article class="panel flex flex-col p-6" data-role="{{ $role }}">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-bg"><x-icon :name="$icon" :size="22" /></span>
                    <div>
                        <h3 class="text-xl font-bold">{{ $title }}</h3>
                        <p class="text-[0.9375rem] text-sub">{{ $desc }}</p>
                    </div>
                </div>

                <ul class="mt-5 space-y-2.5 text-[0.9375rem]">
                    @foreach ($abilities($main) as [$can, $text])
                        <li class="flex items-start gap-2.5 {{ $can ? '' : 'text-sub' }}">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full {{ $can ? 'bg-text text-white' : 'border border-line bg-bg' }}">
                                <x-icon :name="$can ? 'check' : 'x'" :size="12" />
                            </span>
                            <span>{{ $text }}<span class="sr-only">{{ $can ? ' (가능)' : ' (볼 수 없음)' }}</span></span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-auto pt-6"><div class="border-t border-line pt-5">
                    <a href="{{ route('admin.login', ['email' => $main['email']]) }}" class="btn-main w-full gap-2">
                        {{ $main['shop_name'] }} {{ $as }} 체험 <x-icon name="arrow" :size="18" />
                    </a>
                    <p class="mt-2 text-center text-sm text-sub">{{ $main['email'] }}</p>
                    @if ($list->count() > 1)
                        <p class="mt-4 text-sm text-sub">다른 업종 {{ $as }} 보기</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($list->slice(1) as $a)
                                <a href="{{ route('admin.login', ['email' => $a['email']]) }}" class="btn-line min-h-11">{{ $a['shop_name'] ?? $a['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div></div>
            </article>
        @endforeach
    </div>
</section>

{{-- 5. 기능 --}}
<section class="pt-16">
    <h2 class="text-2xl font-bold tracking-tight">들어 있는 기능</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['calendar', '예약 캘린더', '담당자별 보기와 드래그로 시간 이동. 같은 시간 중복 예약과 그룹 정원 초과를 DB 잠금으로 막습니다.'],
            ['user', '손님 예약', '근무표·쉬는 시간·기존 예약을 빼고 가능한 시간만 보여 주며, 저장할 때 서버가 다시 확인합니다.'],
            ['bolt', '실시간 반영', '손님이 예약하면 관리자 캘린더가 새로고침 없이 바뀌고, 보고 있던 시간은 바로 "마감"이 됩니다.'],
            ['card', '보증금 결제', '서버가 금액을 다시 확인하고, 15분 안에 결제하지 않으면 자동 취소, 취소 규정에 따라 환불합니다.'],
            ['users', '고객 관리', '방문 이력·메모·노쇼 기록, 횟수형·금액형 회원권 차감, 예약 완료 포인트 적립.'],
            ['chart', '알림 · 통계', '확정·취소·전날 리마인드 알림과 매출 추이·가동률·상품 비중·재방문율.'],
        ] as [$icon, $title, $desc])
            <div class="panel p-5">
                <div class="flex items-center gap-2.5">
                    <x-icon :name="$icon" :size="20" class="text-sub" />
                    <p class="font-semibold">{{ $title }}</p>
                </div>
                <p class="mt-2 text-[0.9375rem] leading-relaxed text-sub">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection

@section('footer')
    <p>데모 데이터는 매일 {{ config('demo.reset_at') }}에 처음 상태로 돌아갑니다. 실제 개인정보를 입력하지 마세요.</p>
    <p class="mt-1">notedot · 범용 예약·고객관리</p>
@endsection
