@extends('layouts.admin')

@section('title', $customer->name)

@section('content')
<x-admin.page-header :title="$customer->name" :sub="($isOwner ? $customer->formattedPhone() : $customer->maskedPhone()).' · 등록 '.$customer->created_at->format('Y-m-d')">
    <a href="{{ route('admin.customers.index') }}" class="btn-line">목록</a>
</x-admin.page-header>

@if ($errors->any())
    <div class="panel mb-4 border-st-noshow p-4 text-sm text-st-noshow">
        @foreach ($errors->all() as $e)
            <p>{{ $e }}</p>
        @endforeach
    </div>
@endif

{{-- 한눈에 보기 --}}
<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @php
        $cards = [
            ['방문', $stats['visits'].'회'],
            ['마지막 방문', $stats['last_visit'] ? \Carbon\Carbon::parse($stats['last_visit'])->format('Y-m-d') : '-'],
            ['다음 예약', $stats['next'] ? $stats['next']->start_at->format('n/j H:i').' · '.$stats['next']->resource->name : '-'],
            ['노쇼 · 취소', $customer->no_show_count.'회 · '.$stats['cancels'].'회'],
            ['포인트', number_format($customer->points).'P'],
        ];
        if ($isOwner) {
            $cards[] = ['총 이용 금액', number_format($stats['spent']).'원'];
        }
    @endphp
    @foreach ($cards as [$label, $value])
        <div class="panel p-4">
            <p class="text-xs text-sub">{{ $label }}</p>
            <p @class(['mt-1 font-semibold', 'text-st-noshow' => $label === '노쇼 · 취소' && $customer->no_show_count])>{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-6 xl:grid-cols-[1fr_420px]">
    <div class="min-w-0 space-y-6">
        {{-- 기본 정보·메모 --}}
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="panel space-y-4 p-5">
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="customer">
            @php($mine = old('_form') === 'customer')   {{-- 이 화면의 폼 여러 개가 name·memo 칸 이름을 같이 쓰므로, 되돌아온 값이 이 폼 것일 때만 채운다 --}}
            <h2 class="font-semibold">기본 정보</h2>
            <x-admin.field label="이름" name="name">
                <input id="name" name="name" value="{{ $mine ? old('name') : $customer->name }}" class="input max-w-xs" required maxlength="50">
            </x-admin.field>
            <x-admin.field label="메모" name="memo" help="직원끼리 보는 메모입니다. 고객에게는 보이지 않습니다.">
                <textarea id="memo" name="memo" rows="3" class="input" maxlength="2000">{{ $mine ? old('memo') : $customer->memo }}</textarea>
            </x-admin.field>
            <div class="flex flex-wrap items-center gap-3 text-xs text-sub">
                <span>개인정보 동의 {{ $customer->privacy_agreed_at?->format('Y-m-d') ?? '기록 없음' }}</span>
                <span>마케팅 수신 {{ $customer->marketing_agreed_at ? '동의 ('.$customer->marketing_agreed_at->format('Y-m-d').')' : '동의 안 함' }}</span>
                <button class="btn-main ml-auto">저장</button>
            </div>
        </form>

        {{-- 방문 이력 --}}
        <div class="panel overflow-x-auto">
            <h2 class="px-5 pt-5 font-semibold">예약·방문 이력</h2>
            <table class="table mt-2">
                <thead>
                    <tr><th>일시</th><th>{{ term('resource') }}</th><th>{{ term('service') }}</th><th>상태</th>@if ($isOwner)<th class="text-right">금액</th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $r)
                        <tr>
                            <td class="whitespace-nowrap">{{ $r->start_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $r->resource->name }}</td>
                            <td class="text-sub">{{ $r->items->pluck('service_name')->implode(', ') }}{{ $r->party_size > 1 ? ' · '.$r->party_size.'명' : '' }}</td>
                            <td><span class="st-badge st-{{ $r->status }}">{{ $statuses[$r->status] }}</span></td>
                            @if ($isOwner)<td class="text-right">{{ number_format($r->total_price) }}원</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-sub">예약 이력이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $reservations->links() }}</div>
        </div>
    </div>

    <div class="min-w-0 space-y-6">
        {{-- 회원권·정액권 --}}
        <section class="panel space-y-4 p-5">
            <h2 class="font-semibold">회원권 · 정액권</h2>

            @forelse ($passes as $pass)
                @php($usable = ! $pass->isCanceled() && ! $pass->isExpired() && $pass->balance > 0)
                <div @class(['rounded-xl border border-line p-4', 'opacity-60' => ! $usable])>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold">{{ $pass->name }}</p>
                            <p class="text-xs text-sub">
                                {{ \App\Models\Pass::TYPES[$pass->type] }}
                                @if ($pass->service) · {{ $pass->service->name }} 전용 @endif
                                · {{ $pass->expires_on ? $pass->expires_on->format('Y-m-d').'까지' : '기한 없음' }}
                                @if ($isOwner) · 받은 금액 {{ number_format($pass->price) }}원 @endif
                            </p>
                        </div>
                        <span class="badge">{{ $pass->statusLabel() }}</span>
                    </div>
                    <p class="mt-3 text-lg font-bold">{{ $pass->format($pass->balance) }} <span class="text-sm font-normal text-sub">/ {{ $pass->format($pass->total) }}</span></p>
                    <div class="mt-1 h-1.5 rounded-full bg-bg">
                        <div class="h-1.5 rounded-full bg-text" style="width: {{ $pass->total ? min(100, round($pass->balance / $pass->total * 100)) : 0 }}%"></div>
                    </div>

                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-sub">내역 {{ $pass->transactions->count() }}건</summary>
                        <ul class="mt-2 space-y-1">
                            @foreach ($pass->transactions as $tx)
                                <li class="flex gap-2">
                                    <span class="w-20 shrink-0 text-sub">{{ $tx->created_at->format('y.m.d H:i') }}</span>
                                    <span class="w-16 shrink-0">{{ \App\Models\PassTransaction::TYPES[$tx->type] }}</span>
                                    <span @class(['w-20 shrink-0 text-right font-medium', 'text-st-noshow' => $tx->amount < 0])>{{ $tx->amount > 0 ? '+' : '' }}{{ $pass->format($tx->amount) }}</span>
                                    <span class="min-w-0 truncate text-sub">{{ $tx->memo }}{{ $tx->staff ? ' · '.$tx->staff->name : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>

                    @if ($isOwner && ! $pass->isCanceled())
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-sub">잔액 조정 · 권 취소</summary>
                            <form method="POST" action="{{ route('admin.passes.adjust', $pass) }}" class="mt-2 flex flex-wrap gap-2">
                                @csrf
                                <input name="amount" type="number" class="input w-28" placeholder="+1 / -1" required aria-label="조정할 값">
                                <input name="memo" class="input min-w-0 flex-1" placeholder="사유 (필수)" required maxlength="200" aria-label="사유">
                                <button class="btn-line">조정</button>
                            </form>
                            <form method="POST" action="{{ route('admin.passes.cancel', $pass) }}" class="mt-2 flex gap-2"
                                  onsubmit="return confirm('권을 취소하면 남은 {{ $pass->format($pass->balance) }}이(가) 0 이 됩니다. 계속할까요?')">
                                @csrf
                                <input name="memo" class="input min-w-0 flex-1" placeholder="취소 사유 (예: 환불)" maxlength="200" aria-label="취소 사유">
                                <button class="btn-line btn-danger">권 취소</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="text-sm text-sub">발급한 권이 없습니다.</p>
            @endforelse

            @if ($isOwner)
                <details class="rounded-xl border border-dashed border-line p-4" @if (old('_form') === 'pass') open @endif>
                    <summary class="cursor-pointer font-medium">+ 권 발급</summary>
                    <form method="POST" action="{{ route('admin.passes.store', $customer) }}" class="mt-4 grid gap-3 sm:grid-cols-2"
                          x-data="{ type: @js(old('type', 'count')) }">
                        @csrf
                        <input type="hidden" name="_form" value="pass">
                        @php($pf = old('_form') === 'pass')
                        <x-admin.field label="종류" name="type" class="sm:col-span-2">
                            <div class="flex gap-4 text-sm">
                                <label class="flex items-center gap-1"><input type="radio" name="type" value="count" x-model="type"> 횟수형 (10회권)</label>
                                <label class="flex items-center gap-1"><input type="radio" name="type" value="amount" x-model="type"> 금액형 (충전 후 차감)</label>
                            </div>
                        </x-admin.field>
                        <x-admin.field label="이름" name="name" class="sm:col-span-2">
                            <input id="name-pass" name="name" value="{{ $pf ? old('name') : '' }}" class="input" required maxlength="50" :placeholder="type === 'count' ? '예: 10회권' : '예: 50만원 정액권'">
                        </x-admin.field>
                        <x-admin.field label="받은 금액 (원)" name="price">
                            <input id="price" name="price" type="number" min="0" value="{{ old('price') }}" class="input" required>
                        </x-admin.field>
                        <x-admin.field name="total" label="넣을 양">
                            <input id="total" name="total" type="number" min="1" value="{{ old('total') }}" class="input" required :placeholder="type === 'count' ? '횟수 (예: 10)' : '충전 금액 (예: 550000)'">
                        </x-admin.field>
                        <x-admin.field label="전용 {{ term('service') }}" name="service_id" x-show="type === 'count'" help="비우면 모든 {{ term('service') }}에 사용">
                            <select id="service_id" name="service_id" class="input">
                                <option value="">전체</option>
                                @foreach ($countServices as $s)
                                    <option value="{{ $s->id }}" @selected((int) old('service_id') === $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                        <x-admin.field label="만료일" name="expires_on" help="비우면 기한 없음">
                            <input id="expires_on" name="expires_on" type="date" value="{{ old('expires_on') }}" class="input">
                        </x-admin.field>
                        <x-admin.field label="메모" name="memo" class="sm:col-span-2">
                            <input id="memo-pass" name="memo" value="{{ $pf ? old('memo') : '' }}" class="input" maxlength="200">
                        </x-admin.field>
                        <div class="sm:col-span-2"><button class="btn-main">발급</button></div>
                    </form>
                </details>
            @endif
        </section>

        {{-- 포인트 --}}
        <section class="panel space-y-3 p-5">
            <div class="flex items-baseline justify-between">
                <h2 class="font-semibold">포인트</h2>
                <p class="text-lg font-bold">{{ number_format($customer->points) }}P</p>
            </div>
            @if (! $customer->shop->point_rate)
                <p class="text-xs text-sub">매장 설정에서 적립률을 정하면 예약 완료 때 자동으로 적립됩니다.</p>
            @endif
            <ul class="space-y-1 text-sm">
                @forelse ($points as $tx)
                    <li class="flex gap-2">
                        <span class="w-20 shrink-0 text-sub">{{ $tx->created_at->format('y.m.d H:i') }}</span>
                        <span class="w-16 shrink-0">{{ \App\Models\PointTransaction::TYPES[$tx->type] }}</span>
                        <span @class(['w-20 shrink-0 text-right font-medium', 'text-st-noshow' => $tx->amount < 0])>{{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount) }}P</span>
                        <span class="min-w-0 truncate text-sub">{{ $tx->memo }}</span>
                    </li>
                @empty
                    <li class="text-sub">내역이 없습니다.</li>
                @endforelse
            </ul>
            @if ($isOwner)
                <form method="POST" action="{{ route('admin.points.adjust', $customer) }}" class="flex flex-wrap gap-2 border-t border-line pt-3">
                    @csrf
                    <input name="amount" type="number" class="input w-28" placeholder="+1000" required aria-label="조정할 포인트">
                    <input name="memo" class="input min-w-0 flex-1" placeholder="사유 (필수)" required maxlength="200" aria-label="사유">
                    <button class="btn-line">조정</button>
                </form>
            @endif
        </section>
    </div>
</div>
@endsection
