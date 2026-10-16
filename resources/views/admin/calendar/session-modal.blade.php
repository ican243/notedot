{{-- 그룹 일정 창: 새 일정 만들기(반복 포함) / 일정 보기(정원·메모, 신청자, 신청 추가, 일정 취소) --}}
<div x-show="sessionOpen" x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4"
     @keydown.escape.window="closeSession()">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-card p-5 sm:rounded-2xl sm:p-6" @click.outside="closeSession()">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-bold"
                x-text="sessionMode === 'create' ? '새 ' + config.labels.group + ' 일정' : (session ? session.service + ' · ' + session.resource : '')"></h2>
            <div class="flex items-center gap-2">
                <template x-if="sessionMode === 'view' && session">
                    <span class="st-badge" :class="session.canceled ? 'st-canceled' : (session.taken >= session.capacity ? 'st-no_show' : 'st-confirmed')"
                          x-text="session.canceled ? '취소됨' : session.taken + '/' + session.capacity + '명' + (session.taken >= session.capacity ? ' 마감' : '')"></span>
                </template>
                <button type="button" class="text-2xl leading-none text-sub" @click="closeSession()" aria-label="닫기">&times;</button>
            </div>
        </div>

        {{-- 새 일정 --}}
        <template x-if="sessionMode === 'create'">
            <form @submit.prevent="submitSession()" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">{{ term('resource') }}</label>
                        <select class="input" x-model.number="sessionForm.resource_id" @change="onSessionResourceChange()">
                            <template x-for="r in groupResources" :key="r.id">
                                <option :value="r.id" x-text="r.name" :selected="r.id === sessionForm.resource_id"></option>
                            </template>
                        </select>
                        <p class="text-sm text-st-noshow" x-text="err('resource_id')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">{{ term('service') }}</label>
                        <select class="input" x-model.number="sessionForm.service_id">
                            <template x-for="s in sessionServices" :key="s.id">
                                <option :value="s.id" x-text="s.name + ' (' + s.duration + '분 · 정원 ' + s.capacity + ')'" :selected="s.id === sessionForm.service_id"></option>
                            </template>
                        </select>
                        <p class="text-sm text-st-noshow" x-text="err('service_id')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">날짜 <span class="font-normal text-sub" x-show="sessionForm.repeat">(반복 시작일)</span></label>
                        <input type="date" class="input" x-model="sessionForm.date">
                        <p class="text-sm text-st-noshow" x-text="err('date')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">시작</label>
                        <select class="input" x-model="sessionForm.start_time">
                            <template x-for="t in sessionTimeOptions" :key="t">
                                <option :value="t" x-text="t" :selected="t === sessionForm.start_time"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <p class="-mt-3 text-sm text-st-noshow" x-show="err('start_time')" x-text="err('start_time')"></p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">정원</label>
                        <input type="number" min="1" class="input" x-model.number="sessionForm.capacity"
                               :placeholder="sessionService ? '기본 ' + sessionService.capacity + '명' : ''">
                        <p class="text-sm text-st-noshow" x-text="err('capacity')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">메모</label>
                        <input type="text" class="input" x-model="sessionForm.memo" maxlength="500">
                    </div>
                </div>

                {{-- 반복 --}}
                <div class="space-y-2 rounded-xl bg-bg p-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" x-model="sessionForm.repeat" @change="onRepeatToggle()"> 매주 반복
                    </label>
                    <div x-show="sessionForm.repeat" class="space-y-2">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="d in weekdays" :key="d.value">
                                <button type="button" class="rounded-lg border px-3 py-1.5 text-sm"
                                        :class="sessionForm.repeat_days.includes(d.value) ? 'border-text bg-card font-semibold' : 'border-line text-sub'"
                                        @click="toggleRepeatDay(d.value)" x-text="d.label"></button>
                            </template>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <select class="input w-auto" x-model.number="sessionForm.repeat_weeks">
                                <template x-for="w in [1,2,3,4,6,8,12]" :key="w">
                                    <option :value="w" x-text="w + '주'" :selected="w === sessionForm.repeat_weeks"></option>
                                </template>
                            </select>
                            <span class="text-sub">동안 → <span class="font-semibold text-text" x-text="repeatCount"></span>개 일정</span>
                        </div>
                        <p class="text-sm text-st-noshow" x-text="err('repeat_days') || err('repeat_weeks')"></p>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-line" @click="closeSession()">닫기</button>
                    <button class="btn-main" :disabled="saving || !sessionForm.service_id" x-text="saving ? '만드는 중...' : '일정 만들기'"></button>
                </div>
            </form>
        </template>

        {{-- 일정 보기 --}}
        <template x-if="sessionMode === 'view' && session">
            <div class="space-y-5">
                <p class="rounded-lg bg-bg px-3 py-2 text-sm">
                    <span x-text="session.date + ' ' + session.start_time + ' ~ ' + session.end_time"></span> ·
                    1인 <span x-text="won(session.price)"></span>
                    <span x-show="session.canceled" class="text-st-noshow" x-text="' · 취소됨' + (session.cancel_reason ? ' (' + session.cancel_reason + ')' : '')"></span>
                </p>

                <form x-show="!session.canceled" @submit.prevent="saveSession()" class="grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-end">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">정원</label>
                        <input type="number" min="1" class="input" x-model.number="sessionEdit.capacity">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">메모</label>
                        <input type="text" class="input" x-model="sessionEdit.memo" maxlength="500">
                    </div>
                    <button class="btn-line" :disabled="saving">저장</button>
                    <p class="text-sm text-st-noshow sm:col-span-3" x-show="err('capacity')" x-text="err('capacity')"></p>
                </form>

                {{-- 신청자 --}}
                <div>
                    <h3 class="mb-2 text-sm font-semibold">신청 <span class="font-normal text-sub" x-text="session.attendees.length + '건'"></span></h3>
                    <p class="text-sm text-sub" x-show="!session.attendees.length">아직 신청이 없습니다.</p>
                    <ul class="divide-y divide-line rounded-xl border border-line">
                        <template x-for="a in session.attendees" :key="a.id">
                            <li class="flex flex-wrap items-center gap-2 px-3 py-2 text-sm" :class="a.status === 'canceled' ? 'text-sub' : ''">
                                <a :href="a.url" class="font-medium hover:underline" :class="a.status === 'canceled' ? 'line-through' : ''" x-text="a.name"></a>
                                <span class="text-sub" x-text="a.phone + (a.party_size > 1 ? ' · ' + a.party_size + '명' : '') + (a.no_show_count ? ' · 노쇼 ' + a.no_show_count : '')"></span>
                                <span class="st-badge" :class="'st-' + a.status" x-text="config.statuses[a.status]"></span>
                                <span class="ml-auto flex gap-1" x-show="!session.canceled">
                                    <button type="button" class="btn-line px-2 py-1 text-xs" x-show="a.status === 'pending'" @click="attendeeStatus(a, 'confirmed')">확정</button>
                                    <button type="button" class="btn-line px-2 py-1 text-xs" x-show="['confirmed','no_show'].includes(a.status)" @click="attendeeStatus(a, 'completed')">완료</button>
                                    <button type="button" class="btn-line px-2 py-1 text-xs" x-show="['confirmed','completed'].includes(a.status)" @click="attendeeStatus(a, 'no_show')">노쇼</button>
                                    <button type="button" class="btn-line btn-danger px-2 py-1 text-xs" x-show="a.status !== 'canceled'" @click="attendeeStatus(a, 'canceled')">취소</button>
                                    <button type="button" class="btn-line px-2 py-1 text-xs" x-show="a.status === 'canceled'" @click="attendeeStatus(a, 'confirmed')">되돌리기</button>
                                </span>
                                <div class="w-full" x-show="a.passes.used.length || a.passes.usable.length">
                                    @include('admin.calendar.pass-box', ['src' => 'a'])
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- 신청 추가 --}}
                <form x-show="!session.canceled && session.taken < session.capacity" @submit.prevent="addAttendee()"
                      class="space-y-3 rounded-xl bg-bg p-3">
                    <h3 class="text-sm font-semibold">신청 추가 <span class="font-normal text-sub" x-text="'· 남은 자리 ' + (session.capacity - session.taken) + '명'"></span></h3>
                    @include('admin.calendar.customer-picker')
                    <div class="grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-end">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium">인원</label>
                            <input type="number" min="1" :max="session.capacity - session.taken" class="input" x-model.number="form.party_size">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-medium">예약 경로</label>
                            <select class="input" x-model="form.channel">
                                <template x-for="(label, key) in config.channels" :key="key">
                                    <option :value="key" x-text="label" :selected="key === form.channel"></option>
                                </template>
                            </select>
                        </div>
                        <button class="btn-main" :disabled="saving">추가</button>
                    </div>
                    <p class="text-sm text-st-noshow" x-show="err('party_size') || err('status')" x-text="err('party_size') || err('status')"></p>
                </form>

                <div class="flex justify-between gap-2">
                    <button type="button" class="btn-line btn-danger" x-show="!session.canceled" @click="cancelSession()">일정 취소</button>
                    <button type="button" class="btn-line ml-auto" @click="closeSession()">닫기</button>
                </div>

                <template x-if="session.logs.length">
                    <div class="border-t border-line pt-4">
                        <h3 class="mb-2 text-sm font-semibold">변경 이력</h3>
                        <ul class="space-y-1.5 text-xs text-sub">
                            <template x-for="(l, i) in session.logs" :key="i">
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
        </template>
    </div>
</div>
