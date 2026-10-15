@extends('layouts.admin')

@section('title', term('service').' 관리')

@section('content')
<x-admin.page-header :title="term('service').' 관리'" :sub="'고객이 고르는 '.term('service').' 목록입니다.'">
    <a href="{{ route('admin.services.create') }}" class="btn-main">{{ term('service') }} 추가</a>
</x-admin.page-header>

@forelse ($groups as $category => $services)
    <h2 class="mt-6 mb-2 text-sm font-semibold text-sub">{{ $category }}</h2>
    <div class="panel overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>이름</th><th>예약 방식</th><th>시간</th><th>정원</th><th>가격</th><th>보증금</th><th>{{ term('resource') }}</th><th>상태</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($services as $s)
                    <tr>
                        <td class="font-medium">{{ $s->name }}</td>
                        <td>{{ \App\Models\Service::BOOKING_TYPES[$s->booking_type] }}</td>
                        <td>{{ $s->duration_min }}분 @if ($s->buffer_min)<span class="text-sub">+{{ $s->buffer_min }}</span>@endif</td>
                        <td>{{ $s->capacity }}</td>
                        <td>{{ number_format($s->price) }}원</td>
                        <td>{{ $s->deposit ? number_format($s->deposit).'원' : '-' }}</td>
                        <td>{{ $s->resources_count }}</td>
                        <td><span class="badge">{{ $s->is_active ? '판매 중' : '중지' }}</span></td>
                        <td class="text-right"><a href="{{ route('admin.services.edit', $s) }}" class="btn-line">수정</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="panel p-10 text-center text-sub">아직 등록된 {{ term('service') }}이(가) 없습니다.</div>
@endforelse
@endsection
