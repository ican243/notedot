@extends('layouts.admin')

@section('title', term('resource').' 관리')

@section('content')
<x-admin.page-header :title="term('resource').' 관리'" :sub="'예약을 받는 '.term('resource').' 목록입니다. 순서는 캘린더 열 순서가 됩니다.'">
    <a href="{{ route('admin.resources.create') }}" class="btn-main">{{ term('resource') }} 추가</a>
</x-admin.page-header>

@if ($resources->isEmpty())
    <div class="panel p-10 text-center text-sub">아직 등록된 {{ term('resource') }}이(가) 없습니다.</div>
@else
    <div class="panel overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>이름</th><th>종류</th><th>정원</th><th>가능한 {{ term('service') }}</th><th>로그인 계정</th><th>상태</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($resources as $r)
                    <tr>
                        <td>
                            <span class="mr-2 inline-block size-3 rounded-full align-middle" style="background: {{ $r->color }}"></span>
                            <span class="font-medium">{{ $r->name }}</span>
                        </td>
                        <td>{{ \App\Models\Resource::TYPES[$r->type] }}</td>
                        <td>{{ $r->capacity }}@if ($r->max_party) <span class="text-sub">· 최대 {{ $r->max_party }}명</span>@endif</td>
                        <td>{{ $r->services_count }}개</td>
                        <td class="text-sub">{{ $r->staff?->email ?? '-' }}</td>
                        <td><span class="badge">{{ $r->is_active ? '예약 받는 중' : '중지' }}</span></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.schedules.edit', $r) }}" class="btn-line">근무표</a>
                            <a href="{{ route('admin.resources.edit', $r) }}" class="btn-line">수정</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
