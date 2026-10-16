// 데모용 계절 전환 (Alpine 부품). <html data-season> 하나만 바꾸면 색 변수가 통째로 바뀐다
// 저장은 이 브라우저의 localStorage 에만 (못 쓰는 브라우저면 이번 화면에만 적용)
const KEY = 'notedot.season';

export default (fallback) => ({
    value: 'auto',

    init() {
        try { this.value = localStorage.getItem(KEY) || 'auto'; } catch (e) { /* 저장소를 못 쓰는 브라우저 */ }
    },

    apply() {
        document.documentElement.dataset.season = this.value === 'auto' ? fallback : this.value;
        try {
            if (this.value === 'auto') localStorage.removeItem(KEY);
            else localStorage.setItem(KEY, this.value);
        } catch (e) { /* 저장 못 해도 화면에는 적용됨 */ }
    },
});
