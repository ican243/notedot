@extends('layouts.admin')

@section('title', '예약 캘린더')
@section('width', 'max-w-none')

@section('content')
<div x-data="calendarPage(@js($config))">
    <x-admin.page-header title="예약 캘린더" :sub="'빈 칸을 끌어서 새 예약, 예약을 끌어서 시간 이동'">
        @if ($hasResources)
            <button type="button" class="btn-line" x-show="groupResources.length" @click="openSessionCreate()"
                    x-text="'+ ' + config.labels.group + ' 일정'"></button>
            <button type="button" class="btn-main" @click="openCreate()">+ 예약</button>
        @endif
    </x-admin.page-header>

    @unless ($hasResources)
        <div class="panel p-10 text-center text-sub">
            볼 수 있는 {{ term('resource') }}이(가) 없습니다.
            @if (auth('admin')->user()->isOwner())
                <a href="{{ route('admin.resources.create') }}" class="underline">{{ term('resource') }} 추가</a>
            @else
                대표에게 계정과 {{ term('resource') }} 연결을 요청하세요.
            @endif
        </div>
    @else
        {{-- 담당자 필터 --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <template x-for="r in config.resources" :key="r.id">
                <button type="button" @click="toggleResource(r.id)"
                        class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                        :class="selected.includes(r.id) ? 'border-text bg-card' : 'border-line bg-bg text-sub'">
                    <span class="inline-block size-2.5 rounded-full" :style="`background:${r.color}; opacity:${selected.includes(r.id) ? 1 : .3}`"></span>
                    <span x-text="r.name"></span>
                </button>
            </template>
            <label class="ml-auto flex items-center gap-2 text-sm text-sub">
                <input type="checkbox" :checked="showCanceled" @change="toggleCanceled()"> 취소 포함
            </label>
        </div>

        <div class="panel overflow-x-auto p-3 md:p-4">
            <div x-ref="calendar" class="min-w-[640px]"></div>
        </div>

        {{-- 상태 범례 --}}
        <div class="mt-3 flex flex-wrap gap-3 text-xs text-sub">
            <span x-show="groupResources.length">굵은 왼쪽 선 = 그룹 일정 (신청 인원/정원)</span>
            <span>테두리 점선 = 대기</span><span>흐림 = 완료</span><span>빨간 테두리 = 노쇼</span><span>줄 긋기 = 취소</span>
        </div>
    @endunless

    {{-- 예약 창 --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4"
         @keydown.escape.window="close()">
        <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-card p-5 sm:rounded-2xl sm:p-6" @click.outside="close()">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold" x-text="mode === 'create' ? '새 예약' : '예약 #' + detail.id"></h2>
                <div class="flex items-center gap-2">
                    <template x-if="detail">
                        <span class="st-badge" :class="'st-' + detail.status" x-text="config.statuses[detail.status]"></span>
                    </template>
                    <button type="button" class="text-2xl leading-none text-sub" @click="close()" aria-label="닫기">&times;</button>
                </div>
            </div>

            {{-- 상태 바꾸기 (수정 모드) --}}
            <template x-if="detail">
                <div class="mb-5 flex flex-wrap gap-2 rounded-xl bg-bg p-3 text-sm">
                    <template x-if="detail.status !== 'canceled'">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-line" x-show="detail.status === 'pending'" @click="changeStatus('confirmed')">확정</button>
                            <button type="button" class="btn-line" x-show="['confirmed','no_show'].includes(detail.status)" @click="changeStatus('completed')">완료 처리</button>
                            <button type="button" class="btn-line" x-show="['confirmed','completed'].includes(detail.status)" @click="changeStatus('no_show')">노쇼</button>
                            <button type="button" class="btn-line btn-danger" @click="changeStatus('canceled')">예약 취소</button>
                        </div>
                    </template>
                    <template x-if="detail.status === 'canceled'">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sub" x-text="'취소됨' + (detail.cancel_reason ? ' · ' + detail.cancel_reason : '')"></span>
                            <button type="button" class="btn-line" @click="changeStatus('confirmed')">취소 되돌리기</button>
                        </div>
                    </template>
                </div>
            </template>

            {{-- 고객 정보·회원권 (수정 모드) --}}
            <template x-if="detail">
                <div class="mb-5 space-y-2 rounded-xl border border-line p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <a :href="detail.customer.url" class="font-medium underline" x-text="detail.customer.name + ' ' + config.labels.customer + ' 정보'"></a>
                        <span class="text-sub" x-text="'포인트 ' + detail.customer.points.toLocaleString('ko-KR') + 'P'"></span>
                    </div>
                    @include('admin.calendar.pass-box', ['src' => 'detail'])
                    {{-- 보증금 결제 --}}
                    <template x-if="detail.payment">
                        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-2 text-sm">
                            <span class="font-medium" x-text="'보증금 ' + won(detail.payment.amount)"></span>
                            <span class="badge" x-text="detail.payment.label"></span>
                            <span class="text-sub" x-show="detail.payment.paid_at" x-text="detail.payment.paid_at + ' 결제'"></span>
                            <span class="text-sub" x-show="detail.payment.due_at" x-text="detail.payment.due_at + '까지 결제 대기'"></span>
                            <span class="text-sub" x-show="detail.payment.refunded" x-text="won(detail.payment.refunded) + ' 환불'"></span>
                            <span class="text-st-noshow" x-show="detail.payment.failed_reason" x-text="detail.payment.failed_reason"></span>
                            <a class="text-sub underline" x-show="detail.payment.receipt_url" :href="detail.payment.receipt_url" target="_blank" rel="noopener">영수증</a>
                            <button type="button" class="btn-line ml-auto px-2 py-1 text-xs" x-show="config.isOwner && detail.payment.refundable" @click="refundOnly()">환불</button>
                        </div>
                    </template>
                </div>
            </template>

            {{-- 같은 칸에 그룹 일정을 만들고 싶을 때 --}}
            <template x-if="mode === 'create' && groupResources.length">
                <p class="-mt-2 mb-4 text-sm text-sub">
                    정원제로 받으려면
                    <button type="button" class="underline" @click="openSessionCreate(form.date, form.start_time, form.resource_id)"
                            x-text="config.labels.group + ' 일정 만들기'"></button>
                </p>
            </template>

            <form @submit.prevent="submit()" class="space-y-5">
                <fieldset :disabled="detail && detail.status === 'canceled'" class="space-y-5">
                {{-- 고객 --}}
                @include('admin.calendar.customer-picker')

                {{-- 담당 + 날짜/시간 --}}
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">{{ term('resource') }}</label>
                        <select class="input" x-model.number="form.resource_id" @change="onResourceChange()">
                            <template x-for="r in config.resources" :key="r.id">
                                <option :value="r.id" x-text="r.name + (r.active ? '' : ' (중지)')" :selected="r.id === form.resource_id"></option>
                            </template>
                        </select>
                        <p class="text-sm text-st-noshow" x-text="err('resource_id')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">날짜</label>
                        <input type="date" class="input" x-model="form.date">
                        <p class="text-sm text-st-noshow" x-text="err('date')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">시작</label>
                        <select class="input" x-model="form.start_time">
                            <template x-for="t in timeOptions" :key="t">
                                <option :value="t" x-text="t" :selected="t === form.start_time"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <p class="-mt-3 text-sm text-st-noshow" x-show="err('start_time')" x-text="err('start_time')"></p>

                {{-- 상품 --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium">{{ term('service') }}</label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <template x-for="s in availableServices" :key="s.id">
                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                                   :class="form.service_ids.includes(s.id) ? 'border-text' : 'border-line'">
                                <input type="checkbox" :value="s.id" x-model.number="form.service_ids">
                                <span class="flex-1" x-text="s.name"></span>
                                <span class="text-sub" x-text="s.duration + '분'"></span>
                            </label>
                        </template>
                    </div>
                    <p class="text-sm text-sub" x-show="!availableServices.length">이 {{ term('resource') }}에 연결된 {{ term('service') }}이(가) 없습니다.</p>
                    <p class="text-sm text-st-noshow" x-text="err('service_ids')"></p>
                    <p class="rounded-lg bg-bg px-3 py-2 text-sm" x-show="pickedServices.length">
                        <span x-text="form.start_time + ' ~ ' + endTime"></span>
                        (<span x-text="totalMinutes"></span>분) ·
                        <span class="font-semibold" x-text="won(totalPrice)"></span>
                    </p>
                </div>

                {{-- 기타 --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">
                            인원 <span class="font-normal text-sub" x-show="currentResource?.maxParty" x-text="'· 최대 ' + currentResource?.maxParty + '명'"></span>
                        </label>
                        <input type="number" min="1" :max="currentResource?.maxParty || null" class="input" x-model.number="form.party_size">
                        <p class="text-sm text-st-noshow" x-text="err('party_size')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">예약 경로</label>
                        <select class="input" x-model="form.channel">
                            <template x-for="(label, key) in config.channels" :key="key">
                                <option :value="key" x-text="label" :selected="key === form.channel"></option>
                            </template>
                        </select>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <label class="block text-sm font-medium">{{ term('customer') }} 요청사항</label>
                        <input type="text" class="input" x-model="form.customer_request" maxlength="500">
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <label class="block text-sm font-medium">내부 메모 <span class="font-normal text-sub">({{ term('customer') }}에게 보이지 않음)</span></label>
                        <input type="text" class="input" x-model="form.staff_memo" maxlength="500">
                    </div>
                </div>
                </fieldset>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-line" @click="close()">닫기</button>
                    <button class="btn-main" :disabled="saving || (detail && detail.status === 'canceled')"
                            x-text="saving ? '저장 중...' : (mode === 'create' ? '예약 만들기' : '수정 저장')"></button>
                </div>
            </form>

            {{-- 변경 이력 --}}
            <template x-if="detail && detail.logs.length">
                <div class="mt-6 border-t border-line pt-4">
                    <h3 class="mb-2 text-sm font-semibold">변경 이력</h3>
                    <ul class="space-y-1.5 text-xs text-sub">
                        <template x-for="(l, i) in detail.logs" :key="i">
                            <li>
                                <span class="font-medium text-text" x-text="l.at + ' ' + l.by"></span>
                                · <span x-text="l.action"></span>
                                <span x-show="l.changes" x-text="' — ' + logText(l.changes)"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </div>

    @include('admin.calendar.session-modal')

    {{-- 알림 --}}
    <div x-show="toast.show" x-cloak
         class="fixed bottom-5 left-1/2 z-[60] -translate-x-1/2 rounded-xl px-4 py-3 text-sm text-white shadow-lg"
         :class="toast.error ? 'bg-st-noshow' : 'bg-text'" x-text="toast.text"></div>
</div>
@endsection
