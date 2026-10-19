@extends('layouts.public')

@section('title', 'notedot 데모')

@php($industryNames = $shops->pluck('industry')->implode(', '))
@php($owners = $accounts['owner'] ?? collect())
@php($staffs = $accounts['staff'] ?? collect())

@section('content')
{{-- 1. 소개: 왼쪽 제목·버튼, 오른쪽(넓은 화면) 캘린더 미리보기 --}}
<section class="grid items-center gap-8 pt-8 pb-6 sm:pt-10 lg:grid-cols-[1fr_1.1fr] lg:gap-12 lg:pt-10 lg:pb-8">
    <div>
        <p class="on-bg text-sm font-semibold">업종별 예약·고객관리</p>
        <h1 class="mt-3 text-[2rem] leading-tight font-bold tracking-tight sm:text-[2.5rem]">예약 관리, 더 간단하게.</h1>
        <p class="on-bg mt-4 max-w-[32rem] text-base leading-relaxed sm:text-[1.0625rem]">
            온라인 예약, 직원별 캘린더, 결제와 고객 관리를 한곳에서.
            {{ $industryNames }} 데모 매장으로 바로 체험해 보세요.
        </p>
        <div class="mt-7 flex flex-wrap gap-3">
            <a href="#shops" class="btn-main min-h-12 gap-2 px-5">고객 예약 체험하기 <x-icon name="arrow" :size="18" /></a>
            <a href="#admin" class="btn-line min-h-12 px-5 text-base">관리자 기능 둘러보기</a>
        </div>
    </div>

    <div class="hidden lg:block">
        @include('landing.preview')
    </div>
</section>

{{-- 2. 안내 띠: 데모에서 일어나지 않는 일 (한 줄로) --}}
<section aria-label="데모 이용 안내" class="panel px-5 py-3.5">
    <ul class="flex flex-wrap gap-x-7 gap-y-2 text-[0.9375rem]">
        @foreach (array_filter([
            [$demoPayment ? '실제 결제 없음' : '테스트 결제', $demoPayment ? '보증금은 데모 결제창에서 승인·실패만 체험합니다.' : 'PG 테스트 모드라 실제 돈은 나가지 않습니다.'],
            $logMessages ? ['문자 발송 없음', '예약 확정·리마인드 알림은 발송 기록에만 남습니다.'] : null,
            ['매일 '.config('demo.reset_at').' 초기화', '만든 예약·고객·설정은 다음 날 처음 상태로 돌아갑니다.'],
            ['계정 보호', '비밀번호·이메일 변경과 직원 삭제는 막혀 있습니다.'],
        ]) as [$title, $desc])
            <li class="flex items-center gap-2" title="{{ $desc }}">
                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full tint"><x-icon name="check" :size="12" /></span>
                <span class="font-medium">{{ $title }}</span>
            </li>
        @endforeach
    </ul>
</section>

{{-- 3. 업종별 데모 매장 --}}
<section id="shops" class="scroll-mt-6 pt-8 sm:pt-10">
    <h2 class="text-2xl font-bold tracking-tight">업종별 데모 매장</h2>
    <p class="on-bg mt-1.5 max-w-[40rem] text-base leading-relaxed">손님 예약 화면은 휴대폰 기준입니다. 예약하면 관리자 캘린더에 바로 나타납니다. 창 두 개를 나란히 열어 보세요.</p>

    <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($shops as $row)
            <article class="panel flex flex-col overflow-hidden">
                <div class="flex flex-1 flex-col p-5">
                    <div class="flex items-center gap-3">
                        <span class="tint grid h-11 w-11 shrink-0 place-items-center rounded-md">
                            <x-icon :name="$row['icon']" :size="22" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-sub">{{ $row['industry'] }}</p>
                            <h3 class="truncate text-xl font-bold">{{ $row['shop']->name }}</h3>
                        </div>
                    </div>

                    <div class="mt-3.5 flex flex-wrap gap-1.5">
                        @foreach ($row['types'] as $type)
                            <span class="rounded-full border border-line px-2.5 py-0.5 text-sm font-medium">{{ $type }}</span>
                        @endforeach
                        @if ($row['deposit'])
                            <span class="rounded-full border border-line px-2.5 py-0.5 text-sm font-medium">보증금 결제</span>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-2 text-[0.9375rem]">
                        <div class="flex gap-3">
                            <dt class="w-16 shrink-0 text-sub">용어</dt>
                            <dd>{{ $row['terms'] }} <span class="text-sub">· 상품 {{ $row['shop']->services_count }}개</span></dd>
                        </div>
                        <div class="flex gap-3">
                            <dt class="w-16 shrink-0 text-sub">예약 순서</dt>
                            <dd>{{ implode(' → ', $row['steps']) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-5">
                        <a href="{{ route('shop.home', $row['shop']) }}" class="btn-main min-h-12 w-full gap-2" aria-label="{{ $row['shop']->name }} 예약 체험하기">
                            예약 체험하기 <x-icon name="arrow" :size="18" />
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-8 lg:hidden">
        @include('landing.preview')
    </div>
</section>

{{-- 4. 관리자 체험: 역할별 --}}
<section id="admin" class="scroll-mt-6 pt-14">
    <h2 class="text-2xl font-bold tracking-tight">관리자 화면 체험</h2>
    <p class="on-bg mt-2 max-w-[40rem] text-base leading-relaxed">
        같은 매장이라도 역할에 따라 보이는 화면이 다릅니다. 버튼을 누르면 아이디가 채워진 로그인 화면으로 이동합니다.
        비밀번호는 모두 <code class="rounded border border-line bg-card px-1.5 py-0.5 font-semibold text-text">{{ config('demo.password') }}</code> 입니다.
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
                    <span class="tint grid h-11 w-11 shrink-0 place-items-center rounded-md"><x-icon :name="$icon" :size="22" /></span>
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
<section class="pt-14">
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
