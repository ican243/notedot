@extends('layouts.admin')

@section('title', '매장 설정')

@section('content')
<x-admin.page-header title="매장 설정" sub="업종을 고르면 화면 용어와 예약 단위 기본값이 정해집니다." />

<form method="POST" action="{{ route('admin.settings.shop.update') }}" class="space-y-6"
      x-data="{ industry: @js(old('industry', $shop->industry)), presets: @js(collect($industries)->map(fn ($i) => $i['labels'])) }">
    @csrf
    @method('PUT')

    <section class="panel grid gap-5 p-6 sm:grid-cols-2">
        <x-admin.field label="매장 이름" name="name">
            <input id="name" name="name" value="{{ old('name', $shop->name) }}" class="input" required>
        </x-admin.field>

        <x-admin.field label="고객 예약 주소" help="주소는 바꿀 수 없습니다.">
            <input value="{{ route('shop.home', $shop) }}" class="input bg-bg text-sub" readonly>
        </x-admin.field>

        <x-admin.field label="업종" name="industry">
            <select id="industry" name="industry" class="input" x-model="industry">
                @foreach ($industries as $key => $preset)
                    <option value="{{ $key }}">{{ $preset['name'] }}</option>
                @endforeach
            </select>
        </x-admin.field>

        <x-admin.field label="예약 단위" name="slot_unit" help="캘린더 한 칸, 예약 가능 시간 간격">
            <select id="slot_unit" name="slot_unit" class="input">
                @foreach ([10, 15, 20, 30, 60] as $m)
                    <option value="{{ $m }}" @selected(old('slot_unit', $shop->slot_unit) == $m)>{{ $m }}분</option>
                @endforeach
            </select>
        </x-admin.field>

        <x-admin.field label="포인트 적립률 (%)" name="point_rate" help="예약이 완료되면 결제 금액의 몇 %를 적립할지. 0이면 적립하지 않습니다. 회원권으로 처리한 예약은 적립하지 않습니다.">
            <input id="point_rate" name="point_rate" type="number" min="0" max="50" value="{{ old('point_rate', $shop->point_rate) }}" class="input">
        </x-admin.field>
    </section>

    <section class="panel p-6" id="notify">
        <h2 class="font-semibold">{{ term('customer') }} 알림</h2>
        <p class="mt-1 text-sm text-sub">문자·알림톡으로 보낼 알림을 고릅니다. 방문(현장) 예약에는 보내지 않습니다.</p>
        @php($notify = $shop->notifySettings())
        <div class="mt-4 space-y-2 text-sm">
            @foreach (['confirmed' => '예약이 확정되면', 'canceled' => '예약이 취소되면', 'reminder' => '예약 전날 리마인드'] as $key => $label)
                <label class="flex items-center gap-2">
                    <input type="hidden" name="notify[{{ $key }}]" value="0">
                    <input type="checkbox" name="notify[{{ $key }}]" value="1" @checked(old("notify.$key", $notify[$key]))>
                    {{ $label }}
                </label>
            @endforeach
            <x-admin.field label="리마인드 보내는 시각" name="notify.reminder_at" help="예약 전날 이 시각에 보냅니다.">
                <input id="reminder_at" name="notify[reminder_at]" type="time" value="{{ old('notify.reminder_at', $notify['reminder_at']) }}" class="input w-32">
            </x-admin.field>
        </div>
    </section>

    <section class="panel p-6">
        <h2 class="font-semibold">취소·환불 규정</h2>
        <p class="mt-1 text-sm text-sub">보증금을 받은 예약을 취소할 때 환불 금액을 제안하는 기준입니다. 어느 구간에도 들지 않으면 0%입니다. 모두 비우면 기본 규정(24시간 전 100%, 3시간 전 50%)을 씁니다.</p>
        <div class="mt-4 space-y-2">
            @foreach (range(0, 2) as $i)
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span>예약</span>
                    <input name="policy[{{ $i }}][hours]" type="number" min="0" max="720" class="input w-24" aria-label="몇 시간 전"
                           value="{{ old("policy.$i.hours", $policy[$i]['hours'] ?? '') }}">
                    <span>시간 전까지 취소하면</span>
                    <input name="policy[{{ $i }}][percent]" type="number" min="0" max="100" class="input w-20" aria-label="환불 비율"
                           value="{{ old("policy.$i.percent", $policy[$i]['percent'] ?? '') }}">
                    <span>% 환불</span>
                </div>
                @error("policy.$i.hours") <p class="text-sm text-st-noshow">{{ $message }}</p> @enderror
                @error("policy.$i.percent") <p class="text-sm text-st-noshow">{{ $message }}</p> @enderror
            @endforeach
        </div>
    </section>

    <section class="panel p-6">
        <h2 class="font-semibold">화면 용어</h2>
        <p class="mt-1 text-sm text-sub">비워 두면 업종 기본값을 씁니다.</p>
        <div class="mt-4 grid gap-5 sm:grid-cols-3">
            @foreach (['resource' => '예약 대상', 'service' => '예약 상품', 'customer' => '예약자'] as $key => $label)
                <x-admin.field :label="$label" :name="'labels.'.$key">
                    <input name="labels[{{ $key }}]" value="{{ old('labels.'.$key, $shop->labels[$key] ?? '') }}"
                           class="input" :placeholder="presets[industry]?.{{ $key }}">
                </x-admin.field>
            @endforeach
        </div>
    </section>

    <section class="panel p-6">
        <h2 class="font-semibold">영업시간</h2>
        <div class="mt-4 space-y-3">
            @foreach (\App\Models\Shop::DAY_KEYS as $key => $label)
                @php($h = $shop->business_hours[$key] ?? null)
                <div x-data="{ closed: @js((bool) old("hours.$key.closed", ! $h)) }"
                     class="grid grid-cols-[2.5rem_1fr] items-center gap-3 sm:grid-cols-[2.5rem_7rem_1fr]">
                    <span class="font-medium">{{ $label }}</span>
                    <label class="flex items-center gap-2 text-sm text-sub">
                        <input type="hidden" name="hours[{{ $key }}][closed]" value="0">
                        <input type="checkbox" name="hours[{{ $key }}][closed]" value="1" x-model="closed"> 휴무
                    </label>
                    <div class="col-span-2 flex items-center gap-2 sm:col-span-1" x-show="!closed">
                        <input type="time" name="hours[{{ $key }}][open]" value="{{ old("hours.$key.open", $h['open'] ?? '10:00') }}" class="input max-w-36">
                        <span class="text-sub">~</span>
                        <input type="time" name="hours[{{ $key }}][close]" value="{{ old("hours.$key.close", $h['close'] ?? '20:00') }}" class="input max-w-36">
                    </div>
                    @error("hours.$key")
                        <p class="col-span-full text-sm text-st-noshow">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel p-6">
        <x-admin.field label="계절 테마" name="theme" help="자동: 3~5월 봄, 6~8월 여름, 9~11월 가을, 12~2월 겨울">
            <select id="theme" name="theme" class="input max-w-xs">
                @foreach (['auto' => '자동 (날짜 기준)', 'spring' => '봄', 'summer' => '여름', 'autumn' => '가을', 'winter' => '겨울'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('theme', $shop->theme) === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </x-admin.field>
    </section>

    <div class="flex justify-end">
        <button class="btn-main">저장</button>
    </div>
</form>
@endsection
