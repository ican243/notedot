// 보증금 결제 화면 (Alpine 부품)
// 결제창(포트원 또는 데모) → 끝나면 서버에 "확인해 주세요" → 서버가 PG 에 직접 물어보고 예약을 확정한다
import { api } from '../api';

export default (config) => ({
    config,
    busy: false,
    message: '',
    left: 0,          // 남은 초

    get demo() {
        return this.config.gateway.driver === 'demo';
    },
    get clock() {
        const m = Math.floor(this.left / 60);
        const s = this.left % 60;
        return `${m}분 ${String(s).padStart(2, '0')}초`;
    },

    init() {
        const due = new Date(this.config.dueAt).getTime();
        const tick = () => { this.left = Math.max(0, Math.round((due - Date.now()) / 1000)); };
        tick();
        setInterval(tick, 1000);
    },

    // 포트원 결제창 열기. 금액은 표시용이고, 서버는 저장해 둔 금액과 PG 가 받은 금액을 비교한다
    async pay() {
        if (!window.PortOne) {
            this.message = '결제창을 불러오지 못했습니다. 새로고침 후 다시 시도해 주세요.';
            return;
        }
        this.busy = true;
        this.message = '';
        try {
            const res = await window.PortOne.requestPayment({
                storeId: this.config.gateway.storeId,
                channelKey: this.config.gateway.channelKey,
                paymentId: this.config.paymentId,
                orderName: this.config.orderName,
                totalAmount: this.config.amount,
                currency: 'CURRENCY_KRW',
                payMethod: 'CARD',
                customer: this.config.customer,
                redirectUrl: this.config.urls.return,   // 모바일은 결제 뒤 이 주소로 돌아온다
            });
            if (res?.code) {
                // 손님이 결제창을 닫았거나 실패 → 서버 확인 없이 다시 시도할 수 있게
                this.message = res.message || '결제가 완료되지 않았습니다.';
                return;
            }
            await this.check(() => api('POST', this.config.urls.complete));
        } finally {
            this.busy = false;
        }
    },

    async demoPay(result) {
        this.busy = true;
        this.message = '';
        try {
            await this.check(() => api('POST', this.config.urls.demo, { result }));
        } finally {
            this.busy = false;
        }
    },

    async check(request) {
        try {
            const res = await request();
            if (res.redirect) {
                window.location.href = res.redirect;
            } else {
                this.message = res.message;
            }
        } catch (e) {
            this.message = e.message;
        }
    },
});
