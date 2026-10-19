@extends('layouts.shop')

@section('content')
@if ($config['services']->isEmpty())
    <header class="pt-2 pb-5">
        <p class="text-sm"><span class="font-semibold">{{ $shop->name }}</span> <span class="on-bg">· 온라인 예약</span></p>
    </header>
    <div class="panel p-8 text-center text-sub">지금은 온라인 예약을 받고 있지 않습니다.</div>
@else
<div x-data="bookingPage(@js($config))" x-cloak>
    {{-- 상단: 매장 이름 한 줄 + 단계 진행 바 + 이 단계 제목 (배너 없이 짧게) --}}
    <header class="pt-2">
        <p class="text-sm"><span class="font-semibold">{{ $shop->name }}</span> <span class="on-bg">· 온라인 예약</span></p>

        <div class="mt-4" aria-label="예약 단계">
            <p class="text-sm font-semibold sm:hidden">
                <span class="text-btn" x-text="(stepIndex + 1) + '/' + steps.length"></span>
                <span x-text="steps[stepIndex]?.[1]"></span>
            </p>
            <ol class="mt-2 grid gap-1.5 sm:mt-0" :style="`grid-template-columns: repeat(${steps.length}, minmax(0, 1fr))`">
                <template x-for="([key, label], i) in steps" :key="key">
                    <li class="step" :class="i < stepIndex ? 'step-done' : (i === stepIndex ? 'step-now' : '')" :aria-current="i === stepIndex ? 'step' : null">
                        <span class="step-bar"></span>
                        <span class="step-label hidden sm:flex">
                            <span class="step-mark" x-show="i < stepIndex"><x-icon name="check" :size="11" /></span>
                            <span x-text="label"></span>
                        </span>
                        <span class="sr-only" x-text="(i < stepIndex ? '완료: ' : (i === stepIndex ? '현재: ' : '')) + label"></span>
                    </li>
                </template>
            </ol>
        </div>

        <h1 class="mt-5 text-[1.375rem] leading-snug font-bold sm:text-2xl" x-text="heading[0]"></h1>
        <p class="on-bg mt-1 text-[0.9375rem]" x-text="heading[1]"></p>
    </header>

    {{-- 지금까지 고른 것 (고른 것만 보인다) --}}
    <div class="panel mt-4 flex items-start gap-3 px-4 py-3 text-[0.9375rem]" x-show="service && step !== 'service'">
        <dl class="min-w-0 flex-1 space-y-1">
            <div class="flex gap-3">
                <dt class="w-14 shrink-0 text-sub">{{ term('service') }}</dt>
                <dd class="min-w-0 font-medium"><span x-text="service?.name"></span> <span class="text-sub" x-text="service ? '· ' + minutes(service.duration) : ''"></span></dd>
            </div>
            <div class="flex gap-3" x-show="!isGroup && resourcePicked && step !== 'resource'">
                <dt class="w-14 shrink-0 text-sub">{{ term('resource') }}</dt>
                <dd class="font-medium" x-text="resource ? resource.name : '상관없음'"></dd>
            </div>
            <div class="flex gap-3" x-show="!isGroup && time && step === 'info'">
                <dt class="w-14 shrink-0 text-sub">일시</dt>
                <dd class="font-medium" x-text="day ? day.date + ' (' + day.weekday + ') ' + time : ''"></dd>
            </div>
            <div class="flex gap-3" x-show="isGroup && session && step === 'info'">
                <dt class="w-14 shrink-0 text-sub">일정</dt>
                <dd class="font-medium" x-text="session ? session.label + ' ' + session.time + ' · ' + session.resource : ''"></dd>
            </div>
        </dl>
        <button type="button" class="btn-line -my-1 min-h-11 shrink-0 px-3" @click="back()">이전</button>
    </div>

    <p class="mt-4 rounded-lg border border-st-noshow bg-card px-4 py-3 text-[0.9375rem] text-st-noshow" role="alert" x-show="message" x-text="message"></p>

    {{-- 1. 상품 --}}
    <section x-show="step === 'service'" class="mt-5 space-y-5">
        <template x-for="g in categories" :key="g.name">
            <div class="space-y-2">
                <h2 class="on-bg px-1 text-sm font-semibold" x-show="g.name" x-text="g.name"></h2>
                <template x-for="s in g.items" :key="s.id">
                    <button type="button" class="choice" :aria-pressed="service?.id === s.id" @click="pickService(s)">
                        <span class="choice-check" aria-hidden="true"><x-icon name="check" :size="14" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="font-semibold" x-text="s.name"></span>
                                <span class="shrink-0 font-semibold" x-text="won(s.price) + (s.type === 'group' ? ' / 1인' : '')"></span>
                            </span>
                            <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-sub">
                                <span x-text="'소요 ' + minutes(s.duration)"></span>
                                <span x-show="s.type === 'group'">· 정원제</span>
                                <span class="deposit-tag" x-show="s.deposit" x-text="'보증금 ' + won(s.deposit) + ' 별도'"></span>
                            </span>
                            <span class="mt-1 block text-sm text-sub" x-show="s.description" x-text="s.description"></span>
                        </span>
                    </button>
                </template>
            </div>
        </template>
    </section>

    {{-- 2. 담당/공간 --}}
    <section x-show="step === 'resource'" class="mt-5 space-y-2">
        <button type="button" class="choice items-center" x-show="service?.type === 'one_to_one'" :aria-pressed="resourcePicked && resourceId === null" @click="pickResource(null)">
            <span class="choice-check" aria-hidden="true"><x-icon name="check" :size="14" /></span>
            <span class="flex-1 font-semibold">상관없음</span>
            <span class="text-sm text-sub">가장 빠른 시간</span>
        </button>
        <template x-for="r in serviceResources" :key="r.id">
            <button type="button" class="choice items-center" :aria-pressed="resourcePicked && resourceId === r.id" @click="pickResource(r.id)">
                <span class="choice-check" aria-hidden="true"><x-icon name="check" :size="14" /></span>
                <span class="flex-1 font-semibold" x-text="r.name"></span>
                <span class="text-sm text-sub" x-show="r.maxParty" x-text="'최대 ' + r.maxParty + '명'"></span>
            </button>
        </template>
    </section>

    {{-- 3. 날짜·시간 --}}
    <section x-show="step === 'time'" class="mt-5 space-y-3">
        <p class="on-bg text-[0.9375rem]" x-show="loading">가능한 시간을 불러오는 중...</p>
        <div x-show="!loading" class="space-y-3">
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" role="group" aria-label="날짜">
                <template x-for="(d, i) in days" :key="d.date">
                    <button type="button" class="day-chip" :aria-pressed="i === dayIndex" :disabled="!d.times.length && !(closed[d.date] || []).length"
                            :aria-label="d.date + ' ' + d.weekday + (d.times.length ? '' : ' 예약 불가')" @click="pickDay(i)">
                        <span class="text-xs" x-text="d.weekday"></span>
                        <span class="text-base font-semibold" x-text="d.day"></span>
                    </button>
                </template>
            </div>
            <div class="panel p-4">
                <p class="mb-3 text-[0.9375rem] font-semibold" x-text="day ? day.date + ' (' + day.weekday + ')' : ''"></p>
                <div class="grid grid-cols-3 gap-2 min-[400px]:grid-cols-4 sm:grid-cols-5" x-show="dayTimes.length" role="group" aria-label="시간">
                    <template x-for="slot in dayTimes" :key="slot.t">
                        <button type="button" class="time-chip" :disabled="slot.closed" :aria-pressed="!slot.closed && slot.t === time" @click="pickTime(slot.t)">
                            <span class="block" :class="slot.closed && 'line-through'" x-text="slot.t"></span>
                            <span class="block text-xs text-st-noshow" x-show="slot.closed">마감</span>
                        </button>
                    </template>
                </div>
                <p class="text-[0.9375rem] text-sub" x-show="!dayTimes.length">이 날은 예약할 수 있는 시간이 없습니다. 다른 날짜를 골라 주세요.</p>
            </div>
        </div>
    </section>

    {{-- 2. 그룹 일정 --}}
    <section x-show="step === 'session'" class="mt-5 space-y-4">
        <p class="on-bg text-[0.9375rem]" x-show="loading">일정을 불러오는 중...</p>
        <p class="panel p-6 text-center text-[0.9375rem] text-sub" x-show="!loading && !sessions.length">신청할 수 있는 일정이 없습니다.</p>
        <template x-for="g in sessionGroups" :key="g.date">
            <div class="space-y-2">
                <h2 class="on-bg px-1 text-sm font-semibold" x-text="g.label"></h2>
                <template x-for="s in g.items" :key="s.id">
                    <button type="button" class="choice items-center" :disabled="s.left < 1" :aria-pressed="sessionId === s.id" @click="pickSession(s)">
                        <span class="choice-check" aria-hidden="true"><x-icon name="check" :size="14" /></span>
                        <span class="flex-1">
                            <span class="block font-semibold" x-text="s.time"></span>
                            <span class="block text-sm text-sub" x-text="s.resource"></span>
                        </span>
                        <span class="text-sm font-medium" :class="s.left < 1 ? 'text-st-noshow' : (s.left <= 2 ? 'text-st-pending' : 'text-sub')"
                              x-text="s.left < 1 ? '마감' : '남은 자리 ' + s.left"></span>
                    </button>
                </template>
            </div>
        </template>
    </section>

    {{-- [다음]: 휴대폰은 화면 아래 고정, 넓은 화면은 목록 끝. 정보 입력 단계는 키보드를 가리지 않게 고정하지 않는다 --}}
    <div x-show="step !== 'info'">
        <div class="h-24 md:hidden" aria-hidden="true"></div>
        <div class="next-bar">
            <button type="button" class="btn-main min-h-12 w-full text-base" :disabled="!canNext" @click="next()" x-text="nextLabel"></button>
        </div>
    </div>

    {{-- 4. 정보 입력 --}}
    <div x-show="step === 'info'" class="mt-5 space-y-4">
        {{-- 예약 내용 확인 --}}
        <section class="panel divide-y divide-line text-[0.9375rem]" aria-label="예약 내용">
            <div class="flex justify-between gap-4 px-4 py-3">
                <span class="text-sub">총 금액</span>
                <span class="text-lg font-bold" x-text="won(totalPrice)"></span>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3" x-show="service?.deposit">
                <span class="text-sub">보증금 <span class="text-sm">(먼저 결제)</span></span>
                <span class="font-semibold" x-text="service ? won(service.deposit) : ''"></span>
            </div>
            <p class="px-4 py-3 text-sm text-sub" x-show="service?.deposit"
               x-text="'다음 화면에서 보증금을 결제하면 예약이 확정됩니다. ' + config.holdMinutes + '분 안에 결제하지 않으면 자동으로 취소됩니다. 나머지 금액은 매장에서 결제합니다.'"></p>
            <details class="group px-4 py-3">
                <summary class="flex min-h-8 cursor-pointer list-none items-center justify-between font-medium">
                    취소·환불 규정
                    <span class="text-sm text-sub group-open:hidden">보기</span><span class="hidden text-sm text-sub group-open:inline">접기</span>
                </summary>
                <ul class="mt-2 space-y-1 text-sm text-sub">
                    <template x-for="line in config.cancelPolicy" :key="line"><li x-text="'· ' + line"></li></template>
                    <li>· 예약 변경·취소는 매장으로 연락해 주세요.</li>
                </ul>
            </details>
        </section>

        <form @submit.prevent="submit()" class="panel space-y-4 p-5">
            <div class="space-y-1">
                <label for="b-name" class="block text-[0.9375rem] font-medium">이름</label>
                <input id="b-name" class="input" x-model="form.customer_name" autocomplete="name" required maxlength="50">
                <p class="text-sm text-st-noshow" x-text="err('customer_name')"></p>
            </div>
            <div class="space-y-1">
                <label for="b-phone" class="block text-[0.9375rem] font-medium">휴대폰 번호</label>
                <input id="b-phone" type="tel" inputmode="numeric" class="input" x-model="form.customer_phone" placeholder="010-1234-5678" autocomplete="tel" required>
                <p class="text-sm text-st-noshow" x-text="err('customer_phone')"></p>
            </div>
            <div class="space-y-1" x-show="asksParty">
                <label for="b-party" class="block text-[0.9375rem] font-medium">인원 <span class="font-normal text-sub" x-text="'(최대 ' + maxParty + '명)'"></span></label>
                <input id="b-party" type="number" min="1" :max="maxParty" class="input" x-model.number="form.party_size">
                <p class="text-sm text-st-noshow" x-text="err('party_size')"></p>
            </div>
            <div class="space-y-1">
                <label for="b-req" class="block text-[0.9375rem] font-medium">요청사항 <span class="font-normal text-sub">(선택)</span></label>
                <textarea id="b-req" rows="2" class="input" x-model="form.customer_request" maxlength="500"></textarea>
            </div>

            <div class="space-y-3 rounded-lg bg-bg p-3 text-[0.9375rem]">
                <label class="flex items-start gap-2.5">
                    <input type="checkbox" class="mt-1 size-4 accent-btn" x-model="form.privacy">
                    <span><b>[필수]</b> 개인정보 수집·이용 동의
                        <span class="on-bg block text-sm">예약 확인·안내를 위해 이름, 휴대폰 번호를 수집하며 예약 관리 기간 동안 보관합니다.</span>
                    </span>
                </label>
                <p class="text-sm text-st-noshow" x-text="err('privacy')"></p>
                <label class="flex items-start gap-2.5">
                    <input type="checkbox" class="mt-1 size-4 accent-btn" x-model="form.marketing">
                    <span>[선택] 할인·이벤트 소식 받기</span>
                </label>
            </div>

            <button class="btn-main min-h-12 w-full text-base" :disabled="saving"
                    x-text="saving ? '예약하는 중...' : (service?.deposit ? '예약하고 보증금 결제하기' : '예약하기')"></button>
        </form>
    </div>
</div>
@endif
@endsection
