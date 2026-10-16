@extends('layouts.public')

@section('title', 'notedot 데모')

@section('content')
<x-season-card class="mb-8">
    <p class="text-sm opacity-80">범용 예약·고객관리</p>
    <h1 class="mt-1 text-3xl font-bold sm:text-4xl">notedot</h1>
    <p class="mt-10 max-w-xl text-sm opacity-90 sm:text-base">미용실, 필라테스, 스터디룸을 하나의 코드로 운영합니다. 업종에 맞는 말(디자이너·강사·공간)과 예약 방식(1:1·그룹·공간 대여)이 매장 설정으로 바뀝니다.</p>
</x-season-card>

{{-- 1. 손님으로 --}}
<section class="mb-10">
    <h2 class="text-lg font-bold">1. 손님으로 예약해 보기</h2>
    <p class="mt-1 text-sm text-sub">휴대폰 화면 기준입니다. 보증금 상품은 데모 결제창으로 결제까지 해 볼 수 있습니다 (실제 결제 없음).</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        @foreach ($shops as $row)
            <a href="{{ route('shop.home', $row['shop']) }}" class="panel block p-5 transition hover:-translate-y-0.5">
                <p class="text-xs text-sub">{{ $row['industry'] }}</p>
                <p class="mt-1 text-lg font-semibold">{{ $row['shop']->name }}</p>
                <p class="mt-3 text-sm text-sub">{{ $row['terms'] }}</p>
                <p class="text-sm text-sub">{{ $row['types'] }} · {{ $row['shop']->services_count }}개 상품</p>
                <p class="mt-4 text-sm font-medium">예약 화면 열기 →</p>
            </a>
        @endforeach
    </div>
</section>

{{-- 2. 관리자로 --}}
<section class="mb-10">
    <h2 class="text-lg font-bold">2. 관리자로 들어가 보기</h2>
    <p class="mt-1 text-sm text-sub">비밀번호는 모두 <b class="text-text">{{ config('demo.password') }}</b> 입니다. 손님 화면에서 예약하면 관리자 캘린더에 바로 나타납니다.</p>
    <div class="panel mt-4 divide-y divide-line">
        @foreach (config('demo.accounts') as $account)
            <a href="{{ route('admin.login', ['email' => $account['email']]) }}" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 hover:bg-bg">
                <span class="font-medium">{{ $account['label'] }}</span>
                <span class="text-sm text-sub">{{ $account['email'] }} →</span>
            </a>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-sub">직원 계정은 자기 담당 예약·고객만 보고 매출이 보이지 않습니다.</p>
</section>

{{-- 3. 기능 --}}
<section class="mb-10">
    <h2 class="text-lg font-bold">3. 들어 있는 기능</h2>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ([
            ['예약 캘린더', '담당자별 보기, 드래그로 시간 이동, 같은 시간 중복 예약·그룹 정원 초과를 DB 잠금으로 방지'],
            ['손님 예약', '근무표·쉬는 시간·기존 예약을 빼서 가능한 시간만 보여 주고, 저장할 때 서버가 다시 확인'],
            ['실시간', '손님이 예약하면 관리자 캘린더가 새로고침 없이 바뀌고, 보고 있던 시간은 바로 "마감"'],
            ['보증금 결제', '포트원 연결(데모 결제창), 서버 금액 검증, 15분 안에 안 내면 자동 취소, 취소 규정 환불'],
            ['고객 관리', '방문 이력·메모·노쇼, 회원권(횟수·금액형) 차감, 예약 완료 포인트 적립'],
            ['알림 · 통계', '확정·취소·전날 리마인드 알림(발송 기록), 매출 추이·가동률·상품 비중·재방문율'],
        ] as [$title, $desc])
            <div class="panel p-5">
                <p class="font-semibold">{{ $title }}</p>
                <p class="mt-1 text-sm text-sub">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>

<footer class="border-t border-line pt-4 text-xs text-sub">
    데모 데이터는 매일 {{ config('demo.reset_at') }}에 처음 상태로 돌아갑니다. 실제 결제·문자 발송·비밀번호 변경·직원 삭제는 막혀 있습니다.
</footer>
@endsection
