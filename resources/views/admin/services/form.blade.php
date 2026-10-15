@extends('layouts.admin')

@section('title', term('service'))

@section('content')
<x-admin.page-header :title="term('service').($service->exists ? ' 수정' : ' 추가')" />

<form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}"
      class="panel grid max-w-3xl gap-5 p-6 sm:grid-cols-2">
    @csrf
    @if ($service->exists) @method('PUT') @endif

    <x-admin.field label="이름" name="name">
        <input id="name" name="name" value="{{ old('name', $service->name) }}" class="input" required>
    </x-admin.field>

    <x-admin.field label="분류" name="category" help="같은 분류끼리 묶여 보입니다.">
        <input id="category" name="category" value="{{ old('category', $service->category) }}" class="input" list="categories">
        <datalist id="categories">
            @foreach ($categories as $c) <option value="{{ $c }}"> @endforeach
        </datalist>
    </x-admin.field>

    <x-admin.field label="설명" name="description" class="sm:col-span-2">
        <textarea id="description" name="description" rows="2" class="input">{{ old('description', $service->description) }}</textarea>
    </x-admin.field>

    <x-admin.field label="예약 방식" name="booking_type">
        <select id="booking_type" name="booking_type" class="input">
            @foreach ($bookingTypes as $v => $l)
                <option value="{{ $v }}" @selected(old('booking_type', $service->booking_type) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </x-admin.field>

    <x-admin.field label="정원" name="capacity" help="그룹이면 한 타임 최대 인원">
        <input id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $service->capacity) }}" class="input">
    </x-admin.field>

    <x-admin.field label="소요 시간 (분)" name="duration_min">
        <input id="duration_min" name="duration_min" type="number" min="5" step="5" value="{{ old('duration_min', $service->duration_min) }}" class="input">
    </x-admin.field>

    <x-admin.field label="정리 시간 (분)" name="buffer_min" help="끝난 뒤 다음 예약까지 비워 둘 시간">
        <input id="buffer_min" name="buffer_min" type="number" min="0" step="5" value="{{ old('buffer_min', $service->buffer_min) }}" class="input">
    </x-admin.field>

    <x-admin.field label="가격 (원)" name="price">
        <input id="price" name="price" type="number" min="0" step="100" value="{{ old('price', $service->price) }}" class="input">
    </x-admin.field>

    <x-admin.field label="예약 보증금 (원)" name="deposit" help="0이면 결제 없이 예약">
        <input id="deposit" name="deposit" type="number" min="0" step="100" value="{{ old('deposit', $service->deposit) }}" class="input">
    </x-admin.field>

    <x-admin.field label="정렬 순서" name="sort_order">
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $service->sort_order ?? 0) }}" class="input">
    </x-admin.field>

    <x-admin.field label="상태" name="is_active">
        <label class="flex items-center gap-2 py-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active))> 판매 중
        </label>
    </x-admin.field>

    <x-admin.field :label="'담당 '.term('resource')" class="sm:col-span-2">
        @if ($resources->isEmpty())
            <p class="text-sm text-sub">{{ term('resource') }}을(를) 먼저 등록하면 여기서 고를 수 있습니다.</p>
        @else
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach ($resources as $r)
                    <label class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm">
                        <input type="checkbox" name="resource_ids[]" value="{{ $r->id }}"
                               @checked(in_array($r->id, old('resource_ids', $checked)))>
                        <span class="inline-block size-2.5 rounded-full" style="background: {{ $r->color }}"></span>
                        {{ $r->name }}
                    </label>
                @endforeach
            </div>
        @endif
    </x-admin.field>

    <div class="flex items-center justify-between gap-2 sm:col-span-2">
        <a href="{{ route('admin.services.index') }}" class="btn-line">목록</a>
        <button class="btn-main">저장</button>
    </div>
</form>

@if ($service->exists)
    <form method="POST" action="{{ route('admin.services.destroy', $service) }}" class="mt-4 max-w-3xl text-right"
          onsubmit="return confirm('삭제할까요? 지난 예약 기록은 남습니다.')">
        @csrf @method('DELETE')
        <button class="btn-line btn-danger">삭제</button>
    </form>
@endif
@endsection
