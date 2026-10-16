// 고객 예약 화면의 동작 (Alpine 부품)
// 1:1·공간: 상품 → 담당/공간 → 날짜·시간 → 정보 입력 / 그룹: 상품 → 일정 → 정보 입력
// 가능한 시간은 서버(SlotService)가 계산하고, 저장할 때 서버가 다시 확인한다
import { api } from '../api';
import { listen, debounce } from '../realtime';

function emptyForm() {
    return {
        customer_name: '',
        customer_phone: '',
        party_size: 1,
        customer_request: '',
        privacy: false,
        marketing: false,
    };
}

export default (config) => ({
    config,
    step: 'service',        // service | resource | time | session | info
    service: null,
    resourceId: null,       // null = 상관없음 (1:1 에서만)
    days: [],
    dayIndex: 0,
    time: null,
    sessions: [],
    sessionId: null,
    loading: false,
    form: emptyForm(),
    errors: {},
    message: '',
    saving: false,
    closed: {},             // 이 화면을 보는 사이 다른 사람이 잡은 시각 { '2030-01-07': ['10:00'] } → "마감" 표시

    // 실시간: 누가 예약·취소하면 지금 보고 있는 시간·일정 목록을 조용히 다시 불러온다
    init() {
        const refresh = debounce(() => this.refreshQuietly());
        listen(this.config.realtime, {
            events: {
                // time = 1:1·공간 예약, session = 그룹 신청, all = 그룹 일정 자체 (강사 1:1 시간도 바뀜)
                'availability.changed': (e) => {
                    if (e.kind === 'all' || (e.kind === 'session') === this.isGroup) refresh();
                },
            },
            refresh,
        });
    },

    // ── 목록 ──────────────────────────────
    get categories() {
        const groups = [];
        this.config.services.forEach((s) => {
            const name = s.category || '';
            let g = groups.find((x) => x.name === name);
            if (!g) groups.push((g = { name, items: [] }));
            g.items.push(s);
        });
        return groups;
    },
    get serviceResources() {
        if (!this.service) return [];
        return this.config.resources.filter((r) => this.service.resources.includes(r.id));
    },
    get resource() {
        return this.config.resources.find((r) => r.id === this.resourceId) || null;
    },
    get day() {
        return this.days[this.dayIndex] || null;
    },
    // 고른 날짜의 시각 목록 (가능한 시각 + 방금 마감된 시각)
    get dayTimes() {
        if (!this.day) return [];
        const closed = this.closed[this.day.date] || [];
        return [...this.day.times.map((t) => ({ t, closed: false })), ...closed.map((t) => ({ t, closed: true }))]
            .sort((a, b) => a.t.localeCompare(b.t));
    },
    get session() {
        return this.sessions.find((s) => s.id === this.sessionId) || null;
    },
    get sessionGroups() {
        const groups = [];
        this.sessions.forEach((s) => {
            let g = groups.find((x) => x.date === s.date);
            if (!g) groups.push((g = { date: s.date, label: s.label, items: [] }));
            g.items.push(s);
        });
        return groups;
    },
    get isGroup() {
        return this.service?.type === 'group';
    },
    // 인원을 묻는 상품: 공간 대여, 그룹
    get asksParty() {
        return ['space', 'group'].includes(this.service?.type);
    },
    get maxParty() {
        if (this.isGroup) return this.session?.left || 1;
        return this.resource?.maxParty || 20;
    },
    get steps() {
        const label = this.config.labels;
        return this.isGroup
            ? [['service', label.service], ['session', '일정'], ['info', '정보 입력']]
            : [['service', label.service], ['resource', label.resource], ['time', '날짜·시간'], ['info', '정보 입력']];
    },
    get stepIndex() {
        return this.steps.findIndex(([key]) => key === this.step);
    },
    get totalPrice() {
        if (!this.service) return 0;
        return this.service.price * (this.isGroup ? this.form.party_size || 1 : 1);
    },

    // ── 단계 이동 ──────────────────────────
    pickService(s) {
        this.service = s;
        this.resourceId = null;
        this.time = null;
        this.sessionId = null;
        this.errors = {};
        this.message = '';
        if (s.type === 'group') {
            this.go('session');
            this.loadSessions();
        } else if (this.serviceResources.length === 1) {
            this.pickResource(this.serviceResources[0].id);   // 고를 것이 하나뿐이면 건너뛴다
        } else {
            this.go('resource');
        }
    },
    pickResource(id) {
        this.resourceId = id;
        this.time = null;
        this.go('time');
        this.loadSlots();
    },
    pickTime(t) {
        this.time = t;
        this.message = '';
        this.go('info');
    },
    pickSession(s) {
        if (s.left < 1) return;
        this.sessionId = s.id;
        this.form.party_size = 1;
        this.message = '';
        this.go('info');
    },
    back() {
        const order = this.steps.map(([key]) => key);
        let i = this.stepIndex - 1;
        // 담당자가 하나뿐이라 건너뛴 단계는 뒤로 갈 때도 건너뛴다
        if (order[i] === 'resource' && this.serviceResources.length === 1) i--;
        this.go(order[Math.max(0, i)]);
    },
    go(step) {
        this.step = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    // ── 서버에서 불러오기 ───────────────────
    // quiet = 실시간 갱신: 로딩 표시 없이, 고른 날짜를 유지하고, 사라진 시각은 "마감"으로 남긴다
    async loadSlots(quiet = false) {
        const key = this.selectionKey();
        if (!quiet) {
            this.loading = true;
            this.closed = {};
        }
        try {
            const q = new URLSearchParams({ service_id: this.service.id });
            if (this.resourceId) q.set('resource_id', this.resourceId);
            const res = await api('GET', `${this.config.urls.slots}?${q}`);
            if (key !== this.selectionKey()) return;   // 기다리는 사이 다른 상품·담당을 골랐으면 버린다

            if (quiet) {
                const date = this.day?.date;
                this.markClosed(res.days);
                this.days = res.days;
                const i = this.days.findIndex((d) => d.date === date);
                this.dayIndex = i === -1 ? 0 : i;
            } else {
                this.days = res.days;
                const first = this.days.findIndex((d) => d.times.length);
                this.dayIndex = first === -1 ? 0 : first;
            }
        } catch (e) {
            if (!quiet) this.message = e.message;
        } finally {
            if (!quiet) this.loading = false;
        }
    },
    async loadSessions(quiet = false) {
        const key = this.selectionKey();
        if (!quiet) this.loading = true;
        try {
            const list = await api('GET', `${this.config.urls.sessions}?service_id=${this.service.id}`);
            if (key !== this.selectionKey()) return;
            this.sessions = list;

            // 고른 일정의 자리가 그 사이 모자라게 되면 다시 고르게 한다
            if (quiet && this.sessionId && (!this.session || this.session.left < (this.form.party_size || 1))) {
                this.sessionId = null;
                this.message = '방금 다른 분이 신청해서 자리가 모자랍니다. 다른 일정을 골라 주세요.';
                if (this.step === 'info') this.go('session');
            }
        } catch (e) {
            if (!quiet) this.message = e.message;
        } finally {
            if (!quiet) this.loading = false;
        }
    },
    refreshQuietly() {
        if (!this.service || this.loading || this.saving) return;
        if (this.isGroup && ['session', 'info'].includes(this.step)) this.loadSessions(true);
        if (!this.isGroup && ['time', 'info'].includes(this.step)) this.loadSlots(true);
    },
    // 전에는 있었는데 새 목록에 없는 시각 → 마감. 다시 생긴 시각(취소)은 마감에서 뺀다
    markClosed(newDays) {
        const closed = {};
        newDays.forEach((d) => {
            const before = this.days.find((x) => x.date === d.date);
            const gone = (before?.times || []).filter((t) => !d.times.includes(t));
            const still = (this.closed[d.date] || []).filter((t) => !d.times.includes(t));
            const all = [...new Set([...still, ...gone])];
            if (all.length) closed[d.date] = all;
        });
        this.closed = closed;

        if (this.time && this.day && (closed[this.day.date] || []).includes(this.time)) {
            this.message = `방금 다른 분이 ${this.time}을(를) 예약했습니다. 다른 시간을 골라 주세요.`;
            this.time = null;
            if (this.step === 'info') this.go('time');
        }
    },
    selectionKey() {
        return `${this.service?.id}:${this.resourceId}`;
    },

    // ── 예약하기 ───────────────────────────
    async submit() {
        this.saving = true;
        this.errors = {};
        this.message = '';
        const body = {
            ...this.form,
            party_size: this.asksParty ? this.form.party_size : 1,
            service_id: this.service.id,
            resource_id: this.resourceId,
            date: this.isGroup ? null : this.day?.date,
            start_time: this.isGroup ? null : this.time,
            class_session_id: this.isGroup ? this.sessionId : null,
        };
        try {
            const res = await api('POST', this.config.urls.store, body);
            window.location.href = res.redirect;
        } catch (e) {
            this.errors = e.errors || {};
            this.message = e.message;
            // 그 사이 다른 사람이 잡은 시간·일정이면 목록을 새로 받아 다시 고르게 한다
            if (this.errors.start_time) {
                this.go('time');
                await this.loadSlots();
                this.message = e.message;
            } else if (this.errors.class_session_id || (this.isGroup && this.errors.party_size)) {
                await this.loadSessions();
            }
        } finally {
            this.saving = false;
        }
    },

    // ── 표시 도우미 ────────────────────────
    err(field) {
        return this.errors[field]?.[0] || '';
    },
    won(n) {
        return n.toLocaleString('ko-KR') + '원';
    },
    minutes(m) {
        return m >= 60 ? `${Math.floor(m / 60)}시간${m % 60 ? ` ${m % 60}분` : ''}` : `${m}분`;
    },
});
