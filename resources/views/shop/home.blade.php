@extends('layouts.shop')

@section('content')
<x-season-card>
    <p class="text-sm opacity-80">온라인 예약</p>
    <h1 class="mt-1 text-2xl font-bold">{{ $shop->name }}</h1>
    <p class="mt-10 text-sm opacity-80">예약 화면을 준비하고 있습니다.</p>
</x-season-card>
@endsection
