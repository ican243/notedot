@extends('layouts.admin')

@section('title', '직원 계정')

@section('content')
<x-admin.page-header title="직원 계정" sub="관리자 화면에 로그인하는 계정입니다.">
    <a href="{{ route('admin.staffs.create') }}" class="btn-main">직원 추가</a>
</x-admin.page-header>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead>
            <tr><th>이름</th><th>이메일</th><th>권한</th><th>연결된 {{ term('resource') }}</th><th>마지막 로그인</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($staffs as $s)
                <tr>
                    <td class="font-medium">{{ $s->name }}</td>
                    <td>{{ $s->email }}</td>
                    <td><span class="badge">{{ $s->isOwner() ? '대표' : '직원' }}</span></td>
                    <td>{{ $s->resource?->name ?? '-' }}</td>
                    <td class="text-sub">{{ $s->last_login_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td class="text-right"><a href="{{ route('admin.staffs.edit', $s) }}" class="btn-line">수정</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
