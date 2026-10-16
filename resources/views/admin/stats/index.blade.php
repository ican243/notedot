@extends('layouts.admin')

@section('title', '통계')

@section('content')
@php
    $k = $data['kpi'];
    $won = fn (int $n) => number_format($n).'원';
    $pct = fn (?float $n) => $n === null ? '-' : rtrim(rtrim(number_format($n, 1), '0'), '.').'%';
    $delta = $k['revenue_prev'] ? round(($k['revenue'] - $k['revenue_prev']) / $k['revenue_prev'] * 100, 1) : null;
@endphp

<x-admin.page-header title="통계" :sub="$data['from'].' ~ '.$data['to'].' · 매출은 완료된 예약의 이용 금액입니다.'" />

{{-- 기간 고르기 --}}
<form method="GET" class="mb-6 flex flex-wrap items-end gap-2" x-data="{ period: @js($preset) }">
    <select name="period" class="input w-auto" x-model="period" aria-label="기간" @change="period !== 'custom' && $el.form.submit()">
        @foreach ($presets as $v => $l)
            <option value="{{ $v }}" @selected($preset === $v)>{{ $l }}</option>
        @endforeach
    </select>
    <template x-if="period === 'custom'">
        <div class="flex flex-wrap items-center gap-2">
            <input type="date" name="from" value="{{ $data['from'] }}" class="input w-auto" aria-label="시작일">
            <span class="text-sub">~</span>
            <input type="date" name="to" value="{{ $data['to'] }}" class="input w-auto" aria-label="종료일">
            <button class="btn-line">보기</button>
        </div>
    </template>
</form>

{{-- 핵심 숫자 --}}
<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="panel p-5 sm:col-span-2 xl:col-span-1 xl:row-span-2">
        <p class="text-sm text-sub">매출</p>
        <p class="mt-2 text-4xl font-bold sm:text-5xl">{{ number_format($k['revenue']) }}<span class="ml-1 text-xl font-semibold">원</span></p>
        <p class="mt-2 text-sm text-sub">
            @if ($delta === null)
                이전 같은 기간 매출 없음
            @else
                이전 같은 기간보다
                <span class="font-semibold {{ $delta >= 0 ? 'text-st-confirmed' : 'text-st-noshow' }}">{{ $delta >= 0 ? '▲' : '▼' }} {{ $pct(abs($delta)) }}</span>
                <span class="block">({{ $won($k['revenue_prev']) }})</span>
            @endif
        </p>
    </div>
    @foreach ([
        ['방문 (완료 예약)', number_format($k['visits']).'건'],
        ['객단가', $won($k['avg_ticket'])],
        ['재방문율', $pct($data['customers']['revisit_rate']), '방문 고객 '.$data['customers']['total'].'명 중 '.$data['customers']['returning'].'명'],
        ['노쇼율', $pct($k['no_show_rate']), '완료 + 노쇼 기준'],
        ['보증금 순입금', $won($k['deposit_net']), '결제 - 환불'],
        ['회원권 판매', $won($k['pass_sales'])],
    ] as $tile)
        <div class="panel p-5">
            <p class="text-sm text-sub">{{ $tile[0] }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $tile[1] }}</p>
            @isset($tile[2]) <p class="mt-1 text-xs text-sub">{{ $tile[2] }}</p> @endisset
        </div>
    @endforeach
</div>

<div x-data="statsPage(@js($data))" class="grid gap-6 xl:grid-cols-2">
    {{-- 매출 추이 --}}
    <section class="panel p-5 xl:col-span-2">
        <h2 class="font-semibold">{{ count($data['trend']) > 0 && strlen($data['trend'][0]['key']) === 7 ? '월별' : '일별' }} 매출</h2>
        <div class="relative mt-4 h-64"><canvas x-ref="trend" role="img" aria-label="기간별 매출 막대 그래프. 값은 아래 표에 있습니다."></canvas></div>
        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-sub">표로 보기</summary>
            <div class="mt-2 max-h-64 overflow-auto">
                <table class="table">
                    <thead><tr><th>날짜</th><th class="text-right">완료 예약</th><th class="text-right">매출</th></tr></thead>
                    <tbody>
                        @foreach ($data['trend'] as $t)
                            <tr><td>{{ $t['key'] }}</td><td class="text-right">{{ $t['count'] }}</td><td class="text-right">{{ $won($t['value']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    {{-- 담당별 가동률 --}}
    <section class="panel p-5">
        <h2 class="font-semibold">{{ term('resource') }}별 가동률</h2>
        <p class="text-xs text-sub">예약으로 찬 시간 ÷ 일할 수 있는 시간 (오늘까지)</p>
        <div class="relative mt-4" :style="`height: ${Math.max(160, data.resources.length * 40)}px`"><canvas x-ref="resources" role="img" aria-label="{{ term('resource') }}별 가동률 막대 그래프. 값은 아래 표에 있습니다."></canvas></div>
        <div class="mt-3 overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ term('resource') }}</th><th class="text-right">완료</th><th class="text-right">매출</th><th class="text-right">예약 / 근무</th><th class="text-right">가동률</th></tr></thead>
                <tbody>
                    @foreach ($data['resources'] as $r)
                        <tr>
                            <td><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full align-middle" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}</td>
                            <td class="text-right">{{ $r['count'] }}건</td>
                            <td class="text-right">{{ $won($r['revenue']) }}</td>
                            <td class="text-right text-sub">{{ $r['busy_hours'] }} / {{ $r['open_hours'] }}시간</td>
                            <td class="text-right font-medium">{{ $r['utilization'] === null ? '-' : $r['utilization'].'%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 상품별 매출 --}}
    <section class="panel p-5">
        <h2 class="font-semibold">{{ term('service') }}별 매출</h2>
        <p class="text-xs text-sub">매출이 큰 순서 (6개 넘으면 나머지는 기타)</p>
        @if (empty($data['services']))
            <p class="mt-6 text-sm text-sub">이 기간에 완료된 예약이 없습니다.</p>
        @else
            <div class="relative mt-4" :style="`height: ${Math.max(160, data.services.length * 40)}px`"><canvas x-ref="services" role="img" aria-label="{{ term('service') }}별 매출 막대 그래프. 값은 아래 표에 있습니다."></canvas></div>
            <div class="mt-3 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>{{ term('service') }}</th><th class="text-right">건수</th><th class="text-right">매출</th><th class="text-right">비중</th></tr></thead>
                    <tbody>
                        @foreach ($data['services'] as $s)
                            <tr><td>{{ $s['name'] }}</td><td class="text-right">{{ $s['count'] }}</td><td class="text-right">{{ $won($s['revenue']) }}</td><td class="text-right">{{ $pct($s['share']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- 고객·경로 --}}
    <section class="panel p-5">
        <h2 class="font-semibold">방문 {{ term('customer') }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 text-center">
            <div class="rounded-xl bg-bg p-4"><p class="text-sm text-sub">신규</p><p class="mt-1 text-2xl font-semibold">{{ $data['customers']['new'] }}명</p></div>
            <div class="rounded-xl bg-bg p-4"><p class="text-sm text-sub">재방문</p><p class="mt-1 text-2xl font-semibold">{{ $data['customers']['returning'] }}명</p></div>
        </div>
        <p class="mt-2 text-xs text-sub">재방문 = 기간 전에도 방문했거나 기간 안에 두 번 이상 방문한 {{ term('customer') }}</p>
    </section>

    <section class="panel p-5">
        <h2 class="font-semibold">예약 경로</h2>
        @php($channelTotal = max(1, array_sum($data['channels'])))
        <ul class="mt-4 space-y-3 text-sm">
            @foreach ($data['channels'] as $key => $n)
                <li>
                    <div class="flex justify-between"><span>{{ $channels[$key] }}</span><span class="font-medium">{{ $n }}건 · {{ round($n / $channelTotal * 100) }}%</span></div>
                    <div class="mt-1 h-2 rounded-full bg-bg"><div class="h-2 rounded-full" style="width: {{ round($n / $channelTotal * 100) }}%; background: #2a78d6"></div></div>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-sub">취소를 뺀 예약 기준</p>
    </section>
</div>
@endsection
