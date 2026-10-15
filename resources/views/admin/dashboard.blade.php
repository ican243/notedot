@extends('layouts.admin')

@section('title', '대시보드')

@section('content')
<x-season-card>
    <p class="text-sm opacity-80">{{ now()->locale('ko')->isoFormat('YYYY년 M월 D일 dddd') }}</p>
    <h1 class="mt-1 text-2xl font-bold">{{ $shop->name }}</h1>
    <p class="mt-8">{{ $staff->name }}님, 안녕하세요.</p>
</x-season-card>

<div class="mt-8 grid gap-4 sm:grid-cols-3">
    <div class="rounded-card border border-line bg-card p-5">
        <p class="text-sm text-sub">오늘 예약</p>
        <p class="mt-2 text-2xl font-bold">-</p>
    </div>
    <div class="rounded-card border border-line bg-card p-5">
        <p class="text-sm text-sub">이번 주 예약</p>
        <p class="mt-2 text-2xl font-bold">-</p>
    </div>
    @if ($staff->isOwner())
        <div class="rounded-card border border-line bg-card p-5">
            <p class="text-sm text-sub">이번 달 매출</p>
            <p class="mt-2 text-2xl font-bold">-</p>
        </div>
    @endif
</div>
@endsection
