@extends('layouts.shop')

@section('title', '예약 완료')

@section('content')
@php($pending = $reservation->status === 'pending')
<x-season-card class="mb-4">
    <p class="text-sm opacity-80">{{ $shop->name }}</p>
    <h1 class="mt-1 text-2xl font-bold">{{ $pending ? '예약을 접수했습니다' : '예약이 확정되었습니다' }}</h1>
    <p class="mt-8 text-sm opacity-80">{{ $pending ? '매장에서 확인 후 확정 안내를 드립니다.' : '예약 시간에 맞춰 방문해 주세요.' }}</p>
</x-season-card>

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
    <div class="flex gap-4 px-5 py-3">
        <span class="w-16 shrink-0 text-sub">상태</span>
        <span><span class="st-badge st-{{ $reservation->status }}">{{ \App\Models\Reservation::STATUSES[$reservation->status] }}</span></span>
    </div>
</div>

<a href="{{ route('shop.home', $shop) }}" class="btn-line mt-4 block text-center">처음으로</a>
@endsection
