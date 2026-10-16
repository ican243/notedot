@extends('layouts.shop')

@section('title', '보증금 결제')

@section('content')
<x-season-card class="mb-4">
    <p class="text-sm opacity-80">{{ $shop->name }}</p>
    <h1 class="mt-1 text-2xl font-bold">보증금 결제</h1>
    <p class="mt-8 text-sm opacity-80">결제가 확인되면 예약이 확정됩니다.</p>
</x-season-card>

<div x-data="payPage(@js($config))" class="space-y-4" x-cloak>
    <div class="panel divide-y divide-line text-sm">
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
            <p class="text-xs text-sub">포트원 테스트 키를 넣기 전까지 쓰는 연습용 결제창입니다. 실제 결제는 일어나지 않습니다.</p>
            <button type="button" class="btn-main w-full" :disabled="busy || left <= 0" @click="demoPay('paid')">결제 승인</button>
            <button type="button" class="btn-line w-full" :disabled="busy || left <= 0" @click="demoPay('failed')">결제 실패로 해 보기</button>
        </div>
    </template>

    <template x-if="!demo">
        <button type="button" class="btn-main w-full py-3 text-base" :disabled="busy || left <= 0" @click="pay()"
                x-text="busy ? '결제 확인 중...' : '{{ number_format($payment->amount) }}원 결제하기'"></button>
    </template>

    <p class="text-center text-xs text-sub">취소 규정에 따라 보증금이 환불됩니다. 매장에 문의해 주세요.</p>
</div>

@if (($config['gateway']['driver'] ?? '') === 'portone')
    {{-- 포트원 V2 결제창 SDK --}}
    <script src="https://cdn.portone.io/v2/browser-sdk.js"></script>
@endif
@endsection
