@extends('layouts.admin')

@section('title', term('customer').' 관리')

@section('content')
<x-admin.page-header :title="term('customer').' 관리'"
                     :sub="auth('admin')->user()->isOwner() ? '예약한 '.term('customer').' 전체입니다.' : '내가 담당한 예약이 있는 '.term('customer').'만 보입니다.'" />

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ $q }}" class="input max-w-xs" placeholder="이름 또는 전화번호 일부" aria-label="검색">
    <select name="sort" class="input w-auto" aria-label="정렬" onchange="this.form.submit()">
        @foreach (['recent' => '최근 방문순', 'visits' => '방문 많은 순', 'name' => '이름순'] as $v => $l)
            <option value="{{ $v }}" @selected($sort === $v)>{{ $l }}</option>
        @endforeach
    </select>
    <button class="btn-line">검색</button>
    @if ($q)
        <a href="{{ route('admin.customers.index') }}" class="btn-line">전체</a>
    @endif
</form>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead>
            <tr><th>이름</th><th>전화번호</th><th class="text-right">방문</th><th>최근 방문</th><th class="text-right">노쇼</th><th class="text-right">포인트</th><th class="text-right">사용 중인 권</th></tr>
        </thead>
        <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td class="font-medium"><a href="{{ route('admin.customers.show', $c) }}" class="hover:underline">{{ $c->name }}</a></td>
                    <td class="text-sub">{{ $c->maskedPhone() }}</td>
                    <td class="text-right">{{ $c->visits }}회</td>
                    <td class="text-sub">{{ $c->last_visit ? \Carbon\Carbon::parse($c->last_visit)->format('Y-m-d') : '-' }}</td>
                    <td class="text-right {{ $c->no_show_count ? 'text-st-noshow font-semibold' : 'text-sub' }}">{{ $c->no_show_count }}</td>
                    <td class="text-right">{{ number_format($c->points) }}P</td>
                    <td class="text-right">{{ $c->usable_passes ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-sub">{{ $q ? '찾는 '.term('customer').'이(가) 없습니다.' : '아직 '.term('customer').'이(가) 없습니다.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $customers->links() }}</div>
@endsection
