@extends('layouts.shop')

@section('title', '보증금 결제')

@section('content')
<header class="pt-2 pb-5">
    <p class="text-sm"><span class="font-semibold">{{ $shop->name }}</span> <span class="on-bg">· 온라인 예약</span></p>
    <h1 class="mt-4 text-[1.375rem] font-bold sm:text-2xl">보증금 결제</h1>
    <p class="on-bg mt-1 text-[0.9375rem]">결제가 확인되면 예약이 확정됩니다.</p>
</header>

<div x-data="payPage(@js($config))" class="space-y-4" x-cloak>
    <div class="panel divide-y divide-line text-[0.9375rem]">
        @php($start = $reservation->start_at)
        @foreach ([
            '일시' => $start->format('Y-m-d').' ('.['일', '월', '화', '수', '목', '금', '토'][$start->dayOfWeek].') '.$start->format('H:i'),
            term('service') => $reservation->items->pluck('service_name')->implode(', '),
            term('resource') => $reservation->resource->name,
            '이용 금액' => number_format($reservation->total_price).'원 (나머지는 매장에서)',
        ] as $label => $value)
            <div class="flex gap-4 px-5 py-3">
                <span class="w-16 shrink-0 text-sub">{{ $label }}</span>
                <span class="font-medium">{{ $value }}</span>
            </div>
        @endforeach
        <div class="flex items-center gap-4 px-5 py-4">
            <span class="w-16 shrink-0 text-sub">보증금</span>
            <span class="text-xl font-bold">{{ number_format($payment->amount) }}원</span>
        </div>
    </div>

    <p class="text-center text-sm" :class="left <= 60 ? 'text-st-noshow font-semibold' : 'text-sub'">
        <span x-show="left > 0" x-text="clock + ' 안에 결제하지 않으면 예약이 자동으로 취소됩니다.'"></span>
        <span x-show="left <= 0">결제 시간이 지났습니다. 처음부터 다시 예약해 주세요.</span>
    </p>

    <p class="rounded-lg border border-st-noshow bg-card px-4 py-3 text-sm text-st-noshow" x-show="message" x-text="message"></p>

    {{-- 데모 결제창: 실제 돈이 나가지 않는다 --}}
    <template x-if="demo">
        <div class="panel space-y-3 border-dashed p-5">
            <p class="text-sm font-semibold">데모 결제창</p>
            <p class="text-sm text-sub">연습용 결제창입니다. 실제 결제는 일어나지 않습니다.</p>
            <button type="button" class="btn-main min-h-12 w-full text-base" :disabled="busy || left <= 0" @click="demoPay('paid')">결제 승인</button>
            <button type="button" class="btn-line min-h-11 w-full" :disabled="busy || left <= 0" @click="demoPay('failed')">결제 실패로 해 보기</button>
        </div>
    </template>

    <template x-if="!demo">
        <button type="button" class="btn-main min-h-12 w-full text-base" :disabled="busy || left <= 0" @click="pay()"
                x-text="busy ? '결제 확인 중...' : '{{ number_format($payment->amount) }}원 결제하기'"></button>
    </template>

    <details class="panel group px-4 py-3 text-[0.9375rem]">
        <summary class="flex min-h-8 cursor-pointer list-none items-center justify-between font-medium">
            취소·환불 규정
            <span class="text-sm text-sub group-open:hidden">보기</span><span class="hidden text-sm text-sub group-open:inline">접기</span>
        </summary>
        <ul class="mt-2 space-y-1 text-sm text-sub">
            @foreach ($policy as $line)
                <li>· {{ $line }}</li>
            @endforeach
            <li>· 예약 변경·취소는 매장으로 연락해 주세요.</li>
        </ul>
    </details>
</div>

@if (($config['gateway']['driver'] ?? '') === 'portone')
    {{-- 포트원 V2 결제창 SDK --}}
    <script src="https://cdn.portone.io/v2/browser-sdk.js"></script>
@endif
@endsection
