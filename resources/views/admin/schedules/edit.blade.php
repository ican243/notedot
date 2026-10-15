@extends('layouts.admin')

@section('title', $resource->name.' 근무표')

@section('content')
<x-admin.page-header :title="$resource->name.' 근무표'" :sub="'매주 반복되는 시간과, 특정 날짜만 다른 일정을 따로 관리합니다.'">
    <a href="{{ route('admin.resources.index') }}" class="btn-line">목록</a>
</x-admin.page-header>

<div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
    {{-- 매주 반복 --}}
    <form method="POST" action="{{ route('admin.schedules.weekly', $resource) }}" class="panel p-6">
        @csrf @method('PUT')
        <h2 class="font-semibold">매주 반복</h2>

        <div class="mt-4 space-y-4">
            @foreach (\App\Services\ScheduleService::WEEKDAYS as $wd => $day)
                @php($d = $weekly[$wd])
                <div x-data="{ on: @js((bool) old("days.$wd.on", $d['on'])) }" class="border-b border-line pb-4 last:border-0">
                    <label class="flex items-center gap-2 font-medium">
                        <input type="hidden" name="days[{{ $wd }}][on]" value="0">
                        <input type="checkbox" name="days[{{ $wd }}][on]" value="1" x-model="on">
                        {{ $day['label'] }}요일
                        <span class="text-sm font-normal text-sub" x-show="!on">휴무</span>
                    </label>
                    <div x-show="on" class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                        <div class="flex items-center gap-2">
                            <span class="w-10 text-sub">근무</span>
                            <input type="time" name="days[{{ $wd }}][start]" value="{{ old("days.$wd.start", $d['start'] ?? '10:00') }}" class="input">
                            <span class="text-sub">~</span>
                            <input type="time" name="days[{{ $wd }}][end]" value="{{ old("days.$wd.end", $d['end'] ?? '20:00') }}" class="input">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-10 text-sub">휴게</span>
                            <input type="time" name="days[{{ $wd }}][break_start]" value="{{ old("days.$wd.break_start", $d['break_start']) }}" class="input">
                            <span class="text-sub">~</span>
                            <input type="time" name="days[{{ $wd }}][break_end]" value="{{ old("days.$wd.break_end", $d['break_end']) }}" class="input">
                        </div>
                    </div>
                    @error("days.$wd")
                        <p class="mt-1 text-sm text-st-noshow">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-4 text-right"><button class="btn-main">매주 근무표 저장</button></div>
    </form>

    {{-- 특정 날짜 --}}
    <div class="space-y-6">
        <form method="POST" action="{{ route('admin.schedules.exceptions.store', $resource) }}" class="panel space-y-4 p-6"
              x-data="{ type: @js(old('type', 'off')) }">
            @csrf
            <h2 class="font-semibold">날짜 일정 추가</h2>

            <x-admin.field label="날짜" name="date">
                <input id="date" name="date" type="date" min="{{ today()->toDateString() }}" value="{{ old('date') }}" class="input" required>
            </x-admin.field>

            <x-admin.field label="종류" name="type">
                <select id="type" name="type" class="input" x-model="type">
                    <option value="off">휴무</option>
                    <option value="work">특별 근무 (그날만 이 시간)</option>
                </select>
            </x-admin.field>

            <x-admin.field label="시간" name="start_time" help="휴무에서 비워 두면 하루 종일">
                <div class="flex items-center gap-2">
                    <input type="time" name="start_time" value="{{ old('start_time') }}" class="input">
                    <span class="text-sub">~</span>
                    <input type="time" name="end_time" value="{{ old('end_time') }}" class="input">
                </div>
                @error('end_time') <p class="text-sm text-st-noshow">{{ $message }}</p> @enderror
            </x-admin.field>

            <x-admin.field label="메모" name="memo">
                <input id="memo" name="memo" value="{{ old('memo') }}" class="input" placeholder="예: 휴가, 교육">
            </x-admin.field>

            <div class="text-right"><button class="btn-main">추가</button></div>
        </form>

        <div class="panel p-6">
            <h2 class="font-semibold">다가오는 날짜 일정</h2>
            <ul class="mt-3 divide-y divide-line text-sm">
                @forelse ($exceptions as $e)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span>
                            <span class="font-medium">{{ $e->date->locale('ko')->isoFormat('M/D (ddd)') }}</span>
                            <span class="badge">{{ \App\Models\ResourceSchedule::TYPES[$e->type] }}</span>
                            {{ $e->start_time ? substr($e->start_time, 0, 5).'~'.substr($e->end_time, 0, 5) : '하루 종일' }}
                            <span class="text-sub">{{ $e->memo }}</span>
                        </span>
                        <form method="POST" action="{{ route('admin.schedules.exceptions.destroy', [$resource, $e]) }}">
                            @csrf @method('DELETE')
                            <button class="text-sub hover:text-st-noshow">삭제</button>
                        </form>
                    </li>
                @empty
                    <li class="py-2 text-sub">없음</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
