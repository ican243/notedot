@extends('layouts.admin')

@section('title', '대시보드')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-2">
    <div>
        <p class="text-sm text-sub">{{ now()->locale('ko')->isoFormat('YYYY년 M월 D일 dddd') }}</p>
        <h1 class="mt-1 text-2xl font-bold">{{ $shop->name }}</h1>
    </div>
    <p class="text-sub">{{ $staff->name }}님, 안녕하세요.</p>
</div>

<div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as [$label, $value])
        <div class="panel p-5">
            <p class="text-sm text-sub">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $value }}</p>
        </div>
    @endforeach
</div>

<section class="panel mt-6 overflow-x-auto">
    <div class="flex items-center justify-between px-5 pt-5">
        <h2 class="font-semibold">오늘 일정</h2>
        <div class="flex gap-2">
            <a href="{{ route('admin.calendar') }}" class="btn-line">캘린더</a>
            @if ($staff->isOwner())
                <a href="{{ route('admin.stats') }}" class="btn-line">통계</a>
            @endif
        </div>
    </div>
    <table class="table mt-2">
        <thead><tr><th>시간</th><th>{{ term('customer') }}</th><th>{{ term('resource') }}</th><th>{{ term('service') }}</th><th>상태</th></tr></thead>
        <tbody>
            @forelse ($todayList as $r)
                <tr>
                    <td class="whitespace-nowrap">{{ $r->start_at->format('H:i') }} ~ {{ $r->end_at->format('H:i') }}</td>
                    <td><a href="{{ route('admin.customers.show', $r->customer) }}" class="font-medium hover:underline">{{ $r->customer->name }}</a>{{ $r->party_size > 1 ? ' · '.$r->party_size.'명' : '' }}</td>
                    <td>{{ $r->resource->name }}</td>
                    <td class="text-sub">{{ $r->classSession ? $r->classSession->service->name : $r->items->pluck('service_name')->implode(', ') }}</td>
                    <td><span class="st-badge st-{{ $r->status }}">{{ $statuses[$r->status] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-sub">오늘 예약이 없습니다.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
