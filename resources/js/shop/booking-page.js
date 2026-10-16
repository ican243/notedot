// 고객 예약 화면의 동작 (Alpine 부품)
// 1:1·공간: 상품 → 담당/공간 → 날짜·시간 → 정보 입력 / 그룹: 상품 → 일정 → 정보 입력
// 가능한 시간은 서버(SlotService)가 계산하고, 저장할 때 서버가 다시 확인한다
import { api } from '../api';

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
        this.go('info');
    },
    pickSession(s) {
        if (s.left < 1) return;
        this.sessionId = s.id;
        this.form.party_size = 1;
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
    async loadSlots() {
        this.loading = true;
        try {
            const q = new URLSearchParams({ service_id: this.service.id });
            if (this.resourceId) q.set('resource_id', this.resourceId);
            const res = await api('GET', `${this.config.urls.slots}?${q}`);
            this.days = res.days;
            const first = this.days.findIndex((d) => d.times.length);
            this.dayIndex = first === -1 ? 0 : first;
        } catch (e) {
            this.message = e.message;
        } finally {
            this.loading = false;
        }
    },
    async loadSessions() {
        this.loading = true;
        try {
            this.sessions = await api('GET', `${this.config.urls.sessions}?service_id=${this.service.id}`);
        } catch (e) {
            this.message = e.message;
        } finally {
            this.loading = false;
        }
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
