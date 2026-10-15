@extends('layouts.admin')

@section('title', term('resource'))

@section('content')
<x-admin.page-header :title="term('resource').($resource->exists ? ' 수정' : ' 추가')"
                     :sub="$resource->exists ? null : '처음 추가하면 매장 영업시간이 기본 근무표로 들어갑니다.'" />

<form method="POST" action="{{ $resource->exists ? route('admin.resources.update', $resource) : route('admin.resources.store') }}"
      class="panel grid max-w-2xl gap-5 p-6 sm:grid-cols-2">
    @csrf
    @if ($resource->exists) @method('PUT') @endif

    <x-admin.field label="이름" name="name">
        <input id="name" name="name" value="{{ old('name', $resource->name) }}" class="input" required>
    </x-admin.field>

    <x-admin.field label="종류" name="type" help="공간은 로그인 계정과 연결하지 않습니다.">
        <select id="type" name="type" class="input">
            @foreach (\App\Models\Resource::TYPES as $v => $l)
                <option value="{{ $v }}" @selected(old('type', $resource->type) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </x-admin.field>

    <x-admin.field label="캘린더 색" name="color">
        <input id="color" name="color" type="color" value="{{ old('color', $resource->color) }}" class="input h-11 p-1">
    </x-admin.field>

    <x-admin.field label="한 타임 정원" name="capacity" help="1:1이면 1">
        <input id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $resource->capacity) }}" class="input">
    </x-admin.field>

    <x-admin.field label="정렬 순서" name="sort_order" help="작을수록 앞">
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $resource->sort_order ?? 0) }}" class="input">
    </x-admin.field>

    <x-admin.field label="상태" name="is_active">
        <label class="flex items-center gap-2 py-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resource->is_active))> 예약 받는 중
        </label>
    </x-admin.field>

    <x-admin.field :label="'가능한 '.term('service')" class="sm:col-span-2">
        @if ($services->isEmpty())
            <p class="text-sm text-sub">{{ term('service') }}을(를) 먼저 등록하면 여기서 고를 수 있습니다.</p>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($services as $s)
                    <label class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm">
                        <input type="checkbox" name="service_ids[]" value="{{ $s->id }}"
                               @checked(in_array($s->id, old('service_ids', $checked)))>
                        {{ $s->name }} <span class="text-sub">· {{ $s->duration_min }}분</span>
                    </label>
                @endforeach
            </div>
        @endif
    </x-admin.field>

    <div class="flex items-center justify-between gap-2 sm:col-span-2">
        <a href="{{ route('admin.resources.index') }}" class="btn-line">목록</a>
        <button class="btn-main">저장</button>
    </div>
</form>

@if ($resource->exists)
    <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}" class="mt-4 max-w-2xl text-right"
          onsubmit="return confirm('삭제할까요? 지난 예약 기록은 남습니다.')">
        @csrf @method('DELETE')
        <button class="btn-line btn-danger">삭제</button>
    </form>
@endif
@endsection
