// 예약 캘린더 화면의 동작 (Alpine 부품)
// - 캘린더 그리기: calendar-fc.js (FullCalendar)
// - 이 파일: 담당자 필터, 예약 창(만들기/수정), 그룹 일정 창(만들기/보기·신청), 고객 검색, 상태 변경, 서버 통신
import { api } from '../api';
import { listen, debounce } from '../realtime';

const STORAGE_KEY = 'notedot.calendar.resources';

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const hm = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const toMin = (t) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
const fromMin = (m) => `${pad(Math.floor(m / 60) % 24)}:${pad(m % 60)}`;

function emptyForm() {
    return {
        customer_id: null,
        customer_label: '',
        customer_name: '',
        customer_phone: '',
        resource_id: null,
        service_ids: [],
        date: '',
        start_time: '',
        party_size: 1,
        channel: 'phone',
        customer_request: '',
        staff_memo: '',
    };
}

// 화면 요일 순서 (월 ~ 일). value 는 서버와 같은 0=일 ~ 6=토
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 0].map((value) => ({ value, label: '일월화수목금토'[value] }));

function emptySessionForm() {
    return {
        resource_id: null,
        service_id: null,
        date: '',
        start_time: '',
        capacity: null,
        memo: '',
        repeat: false,
        repeat_days: [],
        repeat_weeks: 4,
    };
}

// 지금 이후 가장 가까운 칸
function nextSlot(unit) {
    const d = new Date();
    d.setMinutes(Math.ceil(d.getMinutes() / unit) * unit, 0, 0);
    return d;
}

export default (config) => {
    // FullCalendar 객체는 Alpine 이 감시(반응형)하지 않도록 바깥 변수에 둔다
    let calendar = null;
    let searchSeq = 0;

    return {
        config,
        selected: [],
        showCanceled: false,

        modalOpen: false,
        mode: 'create',          // create | edit
        saving: false,
        form: emptyForm(),
        detail: null,            // 수정 모드일 때 서버에서 받은 예약 정보 (상태, 이력)
        errors: {},

        customerMode: 'search',  // search | new
        customerQuery: '',
        customerResults: [],

        sessionOpen: false,
        sessionMode: 'create',   // create | view
        sessionForm: emptySessionForm(),
        session: null,           // 보기 모드일 때 서버에서 받은 일정 (신청자, 이력)
        sessionEdit: { capacity: 1, memo: '' },
        weekdays: WEEKDAYS,

        toast: { show: false, text: '', error: false },

        async init() {
            this.selected = this.loadSelected();
            const { mountCalendar } = await import('./calendar-fc');
            calendar = mountCalendar(this.$refs.calendar, this);
            this.listenRealtime();
        },

        // ── 실시간: 다른 사람(고객 온라인 예약, 다른 직원)이 바꾸면 캘린더를 다시 불러오고 알림 ──
        listenRealtime() {
            const refresh = debounce(() => calendar?.refetchEvents());
            listen(this.config.realtime, {
                events: {
                    'reservation.changed': (e) => { refresh(); this.announce(e, false); },
                    'session.changed': (e) => { refresh(); this.announce(e, true); },
                },
                refresh,
            });
        },
        announce(e, isSession) {
            if (e.by && e.by === this.config.me) return;                              // 내가 한 일은 이미 알림을 봤다
            if (!this.config.resources.some((r) => r.id === e.resource_id)) return;   // 내가 볼 수 없는 담당자
            const when = `${Number(e.start.slice(5, 7))}/${Number(e.start.slice(8, 10))} ${e.start.slice(11, 16)}`;
            const group = this.config.labels.group;
            let what;
            if (isSession) {
                what = { created: `${group} 일정 추가${e.count > 1 ? ` (${e.count}개)` : ''}`, canceled: `${group} 일정 취소` }[e.action] || `${group} 일정 변경`;
            } else if (e.action === 'created') {
                what = (e.channel === 'online' ? '새 온라인 ' : '새 ') + (e.group ? `${group} 신청` : '예약');
            } else if (e.action === 'status' && e.status === 'canceled') {
                what = '예약 취소';
            } else {
                what = '예약 변경';
            }
            this.notify(`${what} · ${e.resource ?? ''} ${when}`);
        },

        // ── 필터 ──────────────────────────────
        loadSelected() {
            const all = this.config.resources.map((r) => r.id);
            try {
                const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                if (Array.isArray(saved)) {
                    const valid = saved.filter((id) => all.includes(id));
                    if (valid.length) return valid;
                }
            } catch (e) { /* 저장소를 못 쓰는 브라우저면 전부 보기 */ }
            return all;
        },
        toggleResource(id) {
            this.selected = this.selected.includes(id)
                ? this.selected.filter((x) => x !== id)
                : [...this.selected, id];
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(this.selected)); } catch (e) {}
            calendar?.refetchEvents();
        },
        toggleCanceled() {
            this.showCanceled = !this.showCanceled;
            calendar?.refetchEvents();
        },

        // FullCalendar 가 보이는 기간이 바뀔 때마다 부른다
        async fetchEvents(info) {
            if (!this.selected.length) return [];
            const params = new URLSearchParams({ start: info.startStr, end: info.endStr, canceled: this.showCanceled ? 1 : 0 });
            this.selected.forEach((id) => params.append('resources[]', id));
            return api('GET', `${this.config.urls.events}?${params}`);
        },

        // ── 예약 창 열기 ──────────────────────
        openCreate(start = null, end = null) {
            start ??= nextSlot(this.config.slotUnit);   // "+ 예약" 버튼: 지금 이후 가장 가까운 칸
            this.mode = 'create';
            this.detail = null;
            this.errors = {};
            this.form = {
                ...emptyForm(),
                resource_id: this.selected.length === 1 ? this.selected[0] : (this.config.resources[0]?.id ?? null),
                date: ymd(start),
                start_time: hm(start),
            };
            if (end) this.pickServiceByLength((end - start) / 60000);
            this.resetCustomerSearch();
            this.modalOpen = true;
        },

        async openEdit(id) {
            try {
                const r = await api('GET', this.url('reservation', id));
                this.mode = 'edit';
                this.detail = r;
                this.errors = {};
                this.form = {
                    ...emptyForm(),
                    customer_id: r.customer.id,
                    customer_label: `${r.customer.name} · ${r.customer.phone}` + (r.customer.no_show_count ? ` · 노쇼 ${r.customer.no_show_count}회` : ''),
                    resource_id: r.resource_id,
                    service_ids: r.service_ids,
                    date: r.date,
                    start_time: r.start_time,
                    party_size: r.party_size,
                    channel: r.channel,
                    customer_request: r.customer_request ?? '',
                    staff_memo: r.staff_memo ?? '',
                };
                this.resetCustomerSearch();
                this.modalOpen = true;
            } catch (e) {
                this.notify(e.message, true);
            }
        },

        close() {
            this.modalOpen = false;
        },

        // 캘린더 블록 클릭: 그룹 일정이면 일정 창, 아니면 예약 창
        openEvent(event) {
            const p = event.extendedProps;
            if (p.kind === 'session') this.openSession(p.sessionId);
            else this.openEdit(event.id);
        },

        // ── 그룹 일정 ─────────────────────────
        get groupResources() {
            const ids = this.config.groupServices.map((s) => s.id);
            return this.config.resources.filter((r) => r.services.some((id) => ids.includes(id)));
        },
        get sessionServices() {
            const ids = this.config.resources.find((r) => r.id === this.sessionForm.resource_id)?.services || [];
            return this.config.groupServices.filter((s) => s.active && ids.includes(s.id));
        },
        get sessionService() {
            return this.config.groupServices.find((s) => s.id === this.sessionForm.service_id) || null;
        },
        get sessionTimeOptions() {
            const list = [];
            for (let m = toMin(this.config.slotMin); m < toMin(this.config.slotMax); m += this.config.slotUnit) list.push(fromMin(m));
            if (this.sessionForm.start_time && !list.includes(this.sessionForm.start_time)) list.unshift(this.sessionForm.start_time);
            return list;
        },
        // 반복으로 만들어질 개수 (화면 안내용. 서버도 같은 규칙으로 계산)
        get repeatCount() {
            const f = this.sessionForm;
            if (!f.repeat || !f.date) return 1;
            const first = new Date(`${f.date}T00:00:00`);
            let n = 0;
            for (let i = 0; i < 7 * f.repeat_weeks; i++) {
                const d = new Date(first);
                d.setDate(first.getDate() + i);
                if (f.repeat_days.includes(d.getDay())) n++;
            }
            return n;
        },

        openSessionCreate(date = null, time = null, resourceId = null) {
            const slot = nextSlot(this.config.slotUnit);
            const list = this.groupResources;
            const rid = list.some((r) => r.id === resourceId) ? resourceId
                : (list.find((r) => this.selected.includes(r.id))?.id ?? list[0]?.id ?? null);

            this.modalOpen = false;
            this.sessionMode = 'create';
            this.session = null;
            this.errors = {};
            this.sessionForm = { ...emptySessionForm(), resource_id: rid, date: date || ymd(slot), start_time: time || hm(slot) };
            this.onSessionResourceChange();
            this.sessionOpen = true;
        },
        onSessionResourceChange() {
            const list = this.sessionServices;
            if (!list.some((s) => s.id === this.sessionForm.service_id)) this.sessionForm.service_id = list[0]?.id ?? null;
        },
        onRepeatToggle() {
            // 반복을 켜면 시작일의 요일을 먼저 골라 둔다
            if (this.sessionForm.repeat && !this.sessionForm.repeat_days.length && this.sessionForm.date) {
                this.sessionForm.repeat_days = [new Date(`${this.sessionForm.date}T00:00:00`).getDay()];
            }
        },
        toggleRepeatDay(value) {
            const days = this.sessionForm.repeat_days;
            this.sessionForm.repeat_days = days.includes(value) ? days.filter((d) => d !== value) : [...days, value];
        },
        async submitSession() {
            const f = this.sessionForm;
            const body = {
                resource_id: f.resource_id,
                service_id: f.service_id,
                date: f.date,
                start_time: f.start_time,
                capacity: f.capacity || null,
                memo: f.memo,
                repeat_days: f.repeat ? f.repeat_days : [],
                repeat_weeks: f.repeat ? f.repeat_weeks : null,
            };
            await this.send(() => api('POST', this.config.urls.sessionStore, body), () => this.closeSession());
        },

        async openSession(id) {
            try {
                const s = await api('GET', this.url('session', id));
                this.session = s;
                this.sessionMode = 'view';
                this.sessionEdit = { capacity: s.capacity, memo: s.memo ?? '' };
                this.errors = {};
                this.form = emptyForm();          // 신청 추가 칸 (고객 고르기 부품이 form 을 쓴다)
                this.resetCustomerSearch();
                this.modalOpen = false;
                this.sessionOpen = true;
            } catch (e) {
                this.notify(e.message, true);
            }
        },
        closeSession() {
            this.sessionOpen = false;
        },
        async saveSession() {
            const id = this.session.id;
            await this.send(() => api('PUT', this.url('session', id), this.sessionEdit), () => this.openSession(id));
        },
        async addAttendee() {
            const id = this.session.id;
            const f = this.form;
            const body = {
                customer_id: f.customer_id,
                customer_name: f.customer_id ? null : f.customer_name,
                customer_phone: f.customer_id ? null : f.customer_phone,
                party_size: f.party_size,
                channel: f.channel,
            };
            await this.send(() => api('POST', this.url('sessionBook', id), body), () => this.openSession(id));
        },
        async attendeeStatus(a, status) {
            const reason = status === 'canceled' ? window.prompt('취소 사유 (선택)', '') : null;
            if (status === 'canceled' && reason === null) return;
            const id = this.session.id;
            await this.send(() => api('PATCH', this.url('status', a.id), { status, reason }), () => this.openSession(id));
        },
        async cancelSession() {
            const reason = window.prompt('일정을 취소하면 대기·확정 신청도 모두 취소됩니다. 사유 (선택)', '');
            if (reason === null) return;
            const id = this.session.id;
            await this.send(() => api('PATCH', this.url('sessionCancel', id), { reason }), () => this.openSession(id));
        },

        // ── 회원권 (예약 창·일정 창 공용) ────────
        async usePass(reservationId, passId) {
            await this.send(() => api('POST', this.url('reservationPass', reservationId), { pass_id: Number(passId) }), () => this.reopen());
        },
        async restorePass(reservationId) {
            if (!window.confirm('회원권 사용을 취소하고 잔액을 되돌릴까요?')) return;
            await this.send(() => api('DELETE', this.url('reservationPass', reservationId)), () => this.reopen());
        },
        // 지금 열려 있는 창(일정 창 또는 예약 창)을 새로 불러오기
        reopen() {
            return this.sessionOpen && this.session ? this.openSession(this.session.id) : this.openEdit(this.detail.id);
        },

        // 저장 공통: 보내기 → 알림 → 캘린더 새로고침 → 다음 할 일. 실패하면 오류 표시
        async send(request, then) {
            this.saving = true;
            this.errors = {};
            try {
                const res = await request();
                this.notify(res.message);
                calendar?.refetchEvents();
                await then?.();
            } catch (e) {
                this.errors = e.errors || {};
                this.notify(e.message, true);
            } finally {
                this.saving = false;
            }
        },

        // ── 고객 ──────────────────────────────
        resetCustomerSearch() {
            this.customerMode = 'search';
            this.customerQuery = '';
            this.customerResults = [];
        },
        async searchCustomers() {
            const q = this.customerQuery.trim();
            const seq = ++searchSeq;
            if (!q) { this.customerResults = []; return; }
            const list = await api('GET', `${this.config.urls.customers}?q=${encodeURIComponent(q)}`).catch(() => []);
            if (seq === searchSeq) this.customerResults = list;   // 늦게 도착한 옛 결과는 버림
        },
        pickCustomer(c) {
            this.form.customer_id = c.id;
            this.form.customer_label = `${c.name} · ${c.phone}` + (c.no_show_count ? ` · 노쇼 ${c.no_show_count}회` : '');
            this.customerResults = [];
        },
        clearCustomer() {
            this.form.customer_id = null;
            this.form.customer_label = '';
        },

        // ── 상품·시간 계산 (화면 표시용. 진짜 계산은 서버가 다시 한다) ──
        get currentResource() {
            return this.config.resources.find((r) => r.id === this.form.resource_id) || null;
        },
        get availableServices() {
            const ids = this.currentResource?.services || [];
            return this.config.services.filter((s) => ids.includes(s.id) && (s.active || this.form.service_ids.includes(s.id)));
        },
        get pickedServices() {
            return this.form.service_ids.map((id) => this.config.services.find((s) => s.id === id)).filter(Boolean);
        },
        get totalMinutes() {
            return this.pickedServices.reduce((sum, s) => sum + s.duration, 0);
        },
        get totalPrice() {
            return this.pickedServices.reduce((sum, s) => sum + s.price, 0);
        },
        get endTime() {
            if (!this.form.start_time || !this.totalMinutes) return '';
            return fromMin(toMin(this.form.start_time) + this.totalMinutes);
        },
        get timeOptions() {
            const list = [];
            for (let m = toMin(this.config.slotMin); m < toMin(this.config.slotMax); m += this.config.slotUnit) list.push(fromMin(m));
            if (this.form.start_time && !list.includes(this.form.start_time)) list.unshift(this.form.start_time);
            return list;
        },
        // 공간은 끌어서 고른 길이와 시간이 같은 상품을 미리 골라 둔다 (예: 2시간 끌기 → "2시간 이용")
        pickServiceByLength(minutes) {
            if (this.currentResource?.type !== 'space') return;
            const same = this.availableServices.filter((s) => s.active && s.duration === minutes);
            if (same.length === 1) this.form.service_ids = [same[0].id];
        },
        onResourceChange() {
            // 새 담당자가 못 하는 상품은 선택에서 뺀다
            const ids = this.currentResource?.services || [];
            this.form.service_ids = this.form.service_ids.filter((id) => ids.includes(id));
        },
        won(n) {
            return n.toLocaleString('ko-KR') + '원';
        },

        // ── 저장 ──────────────────────────────
        async submit() {
            this.saving = true;
            this.errors = {};
            const body = { ...this.form };
            if (body.customer_id) { body.customer_name = null; body.customer_phone = null; }

            try {
                const res = this.mode === 'create'
                    ? await api('POST', this.config.urls.store, body)
                    : await api('PUT', this.url('reservation', this.detail.id), body);
                this.modalOpen = false;
                this.notify(res.message);
                calendar.refetchEvents();
            } catch (e) {
                this.errors = e.errors || {};
                this.notify(e.message, true);
            } finally {
                this.saving = false;
            }
        },

        async changeStatus(status) {
            let reason = null;
            if (status === 'canceled') {
                reason = window.prompt('취소 사유 (선택)', '');
                if (reason === null) return;   // 창에서 "취소"를 누르면 아무것도 안 함
            }
            try {
                const res = await api('PATCH', this.url('status', this.detail.id), { status, reason });
                this.notify(res.message);
                calendar.refetchEvents();
                await this.openEdit(this.detail.id);   // 창의 상태·이력 새로고침
            } catch (e) {
                this.notify(e.message, true);
            }
        },

        // 드래그로 옮긴 직후: 서버가 거절하면 원래 자리로 되돌린다
        async moveEvent(info) {
            const p = info.event.extendedProps;
            const url = p.kind === 'session' ? this.url('sessionMove', p.sessionId) : this.url('move', info.event.id);
            try {
                const res = await api('PATCH', url, { start: info.event.startStr });
                this.notify(res.message);
            } catch (e) {
                info.revert();
                this.notify(e.message, true);
            }
        },

        // ── 기타 ──────────────────────────────
        url(name, id) {
            return this.config.urls[name].replace('__ID__', id);
        },
        err(field) {
            return this.errors[field]?.[0] || '';
        },
        notify(text, error = false) {
            this.toast = { show: true, text, error };
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast.show = false), error ? 5000 : 2500);
        },
        logText(changes) {
            if (!changes) return '';
            const names = { capacity: '정원', memo: '메모', start_at: '시간', resource: this.config.labels.resource, services: this.config.labels.service, customer: this.config.labels.customer, status: '상태', party_size: '인원', channel: '경로', customer_request: '요청사항', staff_memo: '메모', cancel_reason: '취소 사유' };
            return Object.entries(changes).map(([k, [a, b]]) => {
                const label = names[k] || k;
                if (k === 'status') return `${label}: ${this.config.statuses[a] ?? a} → ${this.config.statuses[b] ?? b}`;
                return `${label}: ${a ?? '-'} → ${b ?? '-'}`;
            }).join(' / ');
        },
    };
};
