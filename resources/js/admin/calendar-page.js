// 예약 캘린더 화면의 동작 (Alpine 부품)
// - 캘린더 그리기: calendar-fc.js (FullCalendar)
// - 이 파일: 담당자 필터, 예약 창(만들기/수정), 고객 검색, 상태 변경, 서버 통신
import { api } from './api';

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

        toast: { show: false, text: '', error: false },

        async init() {
            this.selected = this.loadSelected();
            const { mountCalendar } = await import('./calendar-fc');
            calendar = mountCalendar(this.$refs.calendar, this);
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
            if (!start) {
                // "+ 예약" 버튼: 지금 이후 가장 가까운 칸
                start = new Date();
                const unit = this.config.slotUnit;
                start.setMinutes(Math.ceil(start.getMinutes() / unit) * unit, 0, 0);
            }
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
            try {
                const res = await api('PATCH', this.url('move', info.event.id), { start: info.event.startStr });
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
            const names = { start_at: '시간', resource: this.config.labels.resource, services: this.config.labels.service, customer: this.config.labels.customer, status: '상태', party_size: '인원', channel: '경로', customer_request: '요청사항', staff_memo: '메모', cancel_reason: '취소 사유' };
            return Object.entries(changes).map(([k, [a, b]]) => {
                const label = names[k] || k;
                if (k === 'status') return `${label}: ${this.config.statuses[a] ?? a} → ${this.config.statuses[b] ?? b}`;
                return `${label}: ${a ?? '-'} → ${b ?? '-'}`;
            }).join(' / ');
        },
    };
};
