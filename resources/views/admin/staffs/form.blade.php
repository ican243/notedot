@extends('layouts.admin')

@section('title', $staff->exists ? '직원 수정' : '직원 추가')

@section('content')
@php($demoLocked = $staff->exists && config('demo.enabled'))
<x-admin.page-header :title="$staff->exists ? '직원 수정' : '직원 추가'" />

<form method="POST" action="{{ $staff->exists ? route('admin.staffs.update', $staff) : route('admin.staffs.store') }}"
      class="panel grid max-w-2xl gap-5 p-6 sm:grid-cols-2">
    @csrf
    @if ($staff->exists) @method('PUT') @endif

    <x-admin.field label="이름" name="name">
        <input id="name" name="name" value="{{ old('name', $staff->name) }}" class="input" required>
    </x-admin.field>

    <x-admin.field label="이메일 (로그인 아이디)" name="email">
        <input id="email" name="email" type="email" value="{{ old('email', $staff->email) }}" class="input" required @readonly($demoLocked)>
    </x-admin.field>

    <x-admin.field label="비밀번호" name="password"
                   :help="$demoLocked ? '데모에서는 이메일·비밀번호를 바꿀 수 없습니다.' : ($staff->exists ? '바꿀 때만 입력하세요.' : '8자 이상')">
        <input id="password" name="password" type="password" class="input" autocomplete="new-password" @required(! $staff->exists) @disabled($demoLocked)>
    </x-admin.field>

    <x-admin.field label="권한" name="role" :help="$isMe ? '본인 권한은 바꿀 수 없습니다.' : '직원은 본인 예약·고객만 보고 매출은 볼 수 없습니다.'">
        <select id="role" name="role" class="input" @disabled($isMe)>
            <option value="staff" @selected(old('role', $staff->role) === 'staff')>직원</option>
            <option value="owner" @selected(old('role', $staff->role) === 'owner')>대표</option>
        </select>
        @if ($isMe) <input type="hidden" name="role" value="{{ $staff->role }}"> @endif
    </x-admin.field>

    <x-admin.field :label="'연결할 '.term('resource')" name="resource_id" class="sm:col-span-2"
                   :help="'이 계정으로 로그인하면 캘린더에서 연결된 '.term('resource').'의 예약을 봅니다.'">
        <select id="resource_id" name="resource_id" class="input">
            <option value="">연결 안 함</option>
            @foreach ($resources as $r)
                <option value="{{ $r->id }}" @selected(old('resource_id', $staff->resource_id) == $r->id)>{{ $r->name }}</option>
            @endforeach
        </select>
    </x-admin.field>

    <div class="flex items-center justify-between gap-2 sm:col-span-2">
        <a href="{{ route('admin.staffs.index') }}" class="btn-line">목록</a>
        <button class="btn-main">저장</button>
    </div>
</form>

@if ($staff->exists && ! $isMe && ! $demoLocked)
    <form method="POST" action="{{ route('admin.staffs.destroy', $staff) }}" class="mt-4 max-w-2xl text-right"
          onsubmit="return confirm('이 직원 계정을 삭제할까요?')">
        @csrf @method('DELETE')
        <button class="btn-line btn-danger">계정 삭제</button>
    </form>
@endif
@endsection
