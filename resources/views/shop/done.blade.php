@extends('layouts.shop')

@section('title', '예약 완료')

@section('content')
@php
    $payment = $reservation->payment;
    // 화면 제목·안내: 결제 대기 / 결제 실패로 취소 / 대기(매장 확인) / 확정
    [$title, $sub] = match (true) {
        $reservation->status === 'canceled' => ['예약이 취소되었습니다', $payment?->failed_reason ? '사유: '.rtrim($payment->failed_reason, '.').'. 다시 예약해 주세요.' : '예약이 취소되었습니다.'],
        $payment?->status === 'ready' => ['결제를 기다리고 있습니다', $payment->dueAt()->format('H:i').'까지 보증금을 결제해 주세요.'],
        $reservation->status === 'pending' => ['예약을 접수했습니다', '매장에서 확인 후 확정 안내를 드립니다.'],
        default => ['예약이 확정되었습니다', '예약 시간에 맞춰 방문해 주세요.'],
    };
@endphp
<x-season-card class="mb-4">
    <p class="text-sm opacity-80">{{ $shop->name }}</p>
    <h1 class="mt-1 text-2xl font-bold">{{ $title }}</h1>
    <p class="mt-8 text-sm opacity-80">{{ $sub }}</p>
</x-season-card>

@if ($payment?->status === 'ready')
    <a href="{{ route('shop.pay.show', [$shop, $payment->payment_key]) }}" class="btn-main mb-4 block text-center">보증금 결제하기</a>
@endif

<div class="panel divide-y divide-line text-sm">
    @php($start = $reservation->start_at)
    @foreach ([
        '일시' => $start->format('Y-m-d').' ('.['일', '월', '화', '수', '목', '금', '토'][$start->dayOfWeek].') '.$start->format('H:i').' ~ '.$reservation->end_at->format('H:i'),
        term('service') => $reservation->items->pluck('service_name')->implode(', '),
        term('resource') => $reservation->resource->name,
        '인원' => $reservation->party_size > 1 ? $reservation->party_size.'명' : null,
        '이름' => $reservation->customer->name,
        '연락처' => $reservation->customer->maskedPhone(),
        '금액' => number_format($reservation->total_price).'원'.($reservation->deposit ? ' (보증금 '.number_format($reservation->deposit).'원)' : ''),
        '요청사항' => $reservation->customer_request,
    ] as $label => $value)
        @if ($value)
            <div class="flex gap-4 px-5 py-3">
                <span class="w-16 shrink-0 text-sub">{{ $label }}</span>
                <span class="font-medium">{{ $value }}</span>
            </div>
        @endif
    @endforeach
    @if ($payment)
        <div class="flex gap-4 px-5 py-3">
            <span class="w-16 shrink-0 text-sub">보증금</span>
            <span class="font-medium">{{ number_format($payment->amount) }}원 · {{ \App\Models\Payment::STATUSES[$payment->status] }}</span>
        </div>
    @endif
    <div class="flex gap-4 px-5 py-3">
        <span class="w-16 shrink-0 text-sub">상태</span>
        <span><span class="st-badge st-{{ $reservation->status }}">{{ \App\Models\Reservation::STATUSES[$reservation->status] }}</span></span>
    </div>
</div>

<a href="{{ route('shop.home', $shop) }}" class="btn-line mt-4 block text-center">처음으로</a>
@endsection
