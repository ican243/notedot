@extends('layouts.shop')

@section('content')
<x-season-card class="mb-4">
    <p class="text-sm opacity-80">온라인 예약</p>
    <h1 class="mt-1 text-2xl font-bold">{{ $shop->name }}</h1>
    <p class="mt-8 text-sm opacity-80">원하는 {{ term('service') }}과 시간을 골라 주세요.</p>
</x-season-card>

@if ($config['services']->isEmpty())
    <div class="panel p-8 text-center text-sm text-sub">지금은 온라인 예약을 받고 있지 않습니다.</div>
@else
<div x-data="bookingPage(@js($config))" class="space-y-4" x-cloak>
    {{-- 단계 --}}
    <ol class="flex gap-1 text-xs">
        <template x-for="([key, label], i) in steps" :key="key">
            <li class="flex-1 rounded-full py-1.5 text-center"
                :class="i === stepIndex ? 'bg-text text-white' : (i < stepIndex ? 'bg-card text-text' : 'bg-card text-sub')"
                x-text="(i + 1) + '. ' + label"></li>
        </template>
    </ol>

    {{-- 지금까지 고른 것 --}}
    <div class="panel space-y-1 p-4 text-sm" x-show="service && step !== 'service'">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="font-semibold" x-text="service?.name"></p>
                <p class="text-sub">
                    <span x-text="service ? minutes(service.duration) : ''"></span> ·
                    <span x-text="service ? won(service.price) + (isGroup ? ' (1인)' : '') : ''"></span>
                </p>
                <p class="text-sub" x-show="!isGroup && ['time', 'info'].includes(step)"
                   x-text="resource ? resource.name : '{{ term('resource') }} 상관없음'"></p>
                <p class="font-medium" x-show="step === 'info' && !isGroup && time" x-text="day ? day.date + ' (' + day.weekday + ') ' + time : ''"></p>
                <p class="font-medium" x-show="step === 'info' && isGroup && session" x-text="session ? session.label + ' ' + session.time + ' · ' + session.resource : ''"></p>
            </div>
            <button type="button" class="shrink-0 text-sub underline" @click="back()">이전</button>
        </div>
    </div>

    <p class="rounded-lg border border-st-noshow bg-card px-4 py-3 text-sm text-st-noshow" x-show="message" x-text="message"></p>

    {{-- 1. 상품 --}}
    <section x-show="step === 'service'" class="space-y-4">
        <template x-for="g in categories" :key="g.name">
            <div class="space-y-2">
                <h2 class="px-1 text-sm font-semibold text-sub" x-show="g.name" x-text="g.name"></h2>
                <template x-for="s in g.items" :key="s.id">
                    <button type="button" class="panel block w-full p-4 text-left transition hover:border-text" @click="pickService(s)">
                        <span class="flex items-baseline justify-between gap-3">
                            <span class="font-semibold" x-text="s.name"></span>
                            <span class="shrink-0 font-semibold" x-text="won(s.price)"></span>
                        </span>
                        <span class="mt-1 block text-sm text-sub">
                            <span x-text="minutes(s.duration)"></span>
                            <span x-show="s.type === 'group'"> · 정원제 (1인 가격)</span>
                            <span x-show="s.deposit" x-text="' · 보증금 ' + won(s.deposit)"></span>
                        </span>
                        <span class="mt-1 block text-sm text-sub" x-show="s.description" x-text="s.description"></span>
                    </button>
                </template>
            </div>
        </template>
    </section>

    {{-- 2. 담당/공간 --}}
    <section x-show="step === 'resource'" class="space-y-2">
        <h2 class="px-1 text-sm font-semibold">{{ term('resource') }}을(를) 골라 주세요</h2>
        <button type="button" class="panel flex w-full items-center gap-3 p-4 text-left" x-show="service?.type === 'one_to_one'" @click="pickResource(null)">
            <span class="inline-block size-3 rounded-full bg-line"></span>
            <span class="font-semibold">상관없음</span>
            <span class="ml-auto text-sm text-sub">가장 빠른 시간</span>
        </button>
        <template x-for="r in serviceResources" :key="r.id">
            <button type="button" class="panel flex w-full items-center gap-3 p-4 text-left" @click="pickResource(r.id)">
                <span class="inline-block size-3 rounded-full" :style="`background:${r.color}`"></span>
                <span class="font-semibold" x-text="r.name"></span>
                <span class="ml-auto text-sm text-sub" x-show="r.maxParty" x-text="'최대 ' + r.maxParty + '명'"></span>
            </button>
        </template>
    </section>

    {{-- 3. 날짜·시간 --}}
    <section x-show="step === 'time'" class="space-y-3">
        <p class="text-sm text-sub" x-show="loading">가능한 시간을 불러오는 중...</p>
        <div x-show="!loading" class="space-y-3">
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                <template x-for="(d, i) in days" :key="d.date">
                    <button type="button" class="flex w-14 shrink-0 flex-col items-center rounded-xl border py-2 text-sm"
                            :class="i === dayIndex ? 'border-text bg-text text-white' : (d.times.length ? 'border-line bg-card' : 'border-line bg-card text-sub opacity-40')"
                            :disabled="!d.times.length" @click="dayIndex = i; time = null">
                        <span class="text-xs" x-text="d.weekday"></span>
                        <span class="text-base font-semibold" x-text="d.day"></span>
                    </button>
                </template>
            </div>
            <div class="panel p-4">
                <p class="mb-3 text-sm font-semibold" x-text="day ? day.date + ' (' + day.weekday + ')' : ''"></p>
                <div class="grid grid-cols-4 gap-2" x-show="dayTimes.length">
                    <template x-for="slot in dayTimes" :key="slot.t">
                        <button type="button" class="rounded-lg border py-2 text-sm font-medium" :disabled="slot.closed"
                                :class="slot.closed ? 'border-line bg-bg text-sub' : 'border-line hover:border-text'" @click="pickTime(slot.t)">
                            <span class="block" :class="slot.closed && 'line-through'" x-text="slot.t"></span>
                            <span class="block text-xs text-st-noshow" x-show="slot.closed">마감</span>
                        </button>
                    </template>
                </div>
                <p class="text-sm text-sub" x-show="!dayTimes.length">예약 가능한 시간이 없습니다.</p>
            </div>
        </div>
    </section>

    {{-- 2. 그룹 일정 --}}
    <section x-show="step === 'session'" class="space-y-3">
        <p class="text-sm text-sub" x-show="loading">일정을 불러오는 중...</p>
        <p class="panel p-6 text-center text-sm text-sub" x-show="!loading && !sessions.length">신청할 수 있는 일정이 없습니다.</p>
        <template x-for="g in sessionGroups" :key="g.date">
            <div class="space-y-2">
                <h2 class="px-1 text-sm font-semibold" x-text="g.label"></h2>
                <template x-for="s in g.items" :key="s.id">
                    <button type="button" class="panel flex w-full items-center gap-3 p-4 text-left" :disabled="s.left < 1"
                            :class="s.left < 1 ? 'opacity-50' : ''" @click="pickSession(s)">
                        <span>
                            <span class="block font-semibold" x-text="s.time"></span>
                            <span class="block text-sm text-sub" x-text="s.resource"></span>
                        </span>
                        <span class="ml-auto text-sm font-medium" :class="s.left < 1 ? 'text-st-noshow' : (s.left <= 2 ? 'text-st-pending' : 'text-sub')"
                              x-text="s.left < 1 ? '마감' : '남은 자리 ' + s.left"></span>
                    </button>
                </template>
            </div>
        </template>
    </section>

    {{-- 4. 정보 입력 --}}
    <form x-show="step === 'info'" @submit.prevent="submit()" class="panel space-y-4 p-5">
        <div class="space-y-1">
            <label for="b-name" class="block text-sm font-medium">이름</label>
            <input id="b-name" class="input" x-model="form.customer_name" autocomplete="name" required maxlength="50">
            <p class="text-sm text-st-noshow" x-text="err('customer_name')"></p>
        </div>
        <div class="space-y-1">
            <label for="b-phone" class="block text-sm font-medium">휴대폰 번호</label>
            <input id="b-phone" type="tel" inputmode="numeric" class="input" x-model="form.customer_phone" placeholder="010-1234-5678" autocomplete="tel" required>
            <p class="text-sm text-st-noshow" x-text="err('customer_phone')"></p>
        </div>
        <div class="space-y-1" x-show="asksParty">
            <label for="b-party" class="block text-sm font-medium">인원 <span class="font-normal text-sub" x-text="'(최대 ' + maxParty + '명)'"></span></label>
            <input id="b-party" type="number" min="1" :max="maxParty" class="input" x-model.number="form.party_size">
            <p class="text-sm text-st-noshow" x-text="err('party_size')"></p>
        </div>
        <div class="space-y-1">
            <label for="b-req" class="block text-sm font-medium">요청사항 <span class="font-normal text-sub">(선택)</span></label>
            <textarea id="b-req" rows="2" class="input" x-model="form.customer_request" maxlength="500"></textarea>
        </div>

        <div class="space-y-2 rounded-xl bg-bg p-3 text-sm">
            <label class="flex items-start gap-2">
                <input type="checkbox" class="mt-0.5" x-model="form.privacy">
                <span><b>[필수]</b> 개인정보 수집·이용 동의
                    <span class="block text-xs text-sub">예약 확인·안내를 위해 이름, 휴대폰 번호를 수집하며 예약 관리 기간 동안 보관합니다.</span>
                </span>
            </label>
            <p class="text-st-noshow" x-text="err('privacy')"></p>
            <label class="flex items-start gap-2">
                <input type="checkbox" class="mt-0.5" x-model="form.marketing">
                <span>[선택] 할인·이벤트 소식 받기</span>
            </label>
        </div>

        <div class="flex items-center justify-between border-t border-line pt-4 text-sm">
            <span class="text-sub">결제 예정 금액</span>
            <span class="text-lg font-bold" x-text="won(totalPrice)"></span>
        </div>
        <p class="-mt-2 text-xs text-sub" x-show="service?.deposit">보증금이 있는 {{ term('service') }}은(는) 매장 확인 후 예약이 확정됩니다.</p>

        <button class="btn-main w-full py-3 text-base" :disabled="saving" x-text="saving ? '예약하는 중...' : '예약하기'"></button>
    </form>
</div>
@endif
@endsection
