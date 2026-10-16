// 통계 화면 차트 (Chart.js). 한 가지 값만 보여 주는 차트라 색은 하나, 막대는 얇게, 값은 툴팁과 아래 표로

const BAR = '#2a78d6';     // 막대 색 (한 계열)
const GRID = '#ececec';    // --line
const INK = '#5b6270';     // --text-sub

// 120000 → 12만, 1500000 → 150만
const man = (n) => (Math.abs(n) >= 10000 ? `${Math.round(n / 1000) / 10}만` : n.toLocaleString('ko-KR'));
const won = (n) => `${n.toLocaleString('ko-KR')}원`;

export default (data) => ({
    data,

    async init() {
        const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');
        Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);
        Chart.defaults.font.family = 'Pretendard, sans-serif';
        Chart.defaults.color = INK;
        Chart.defaults.animation = false;

        const bar = { backgroundColor: BAR, hoverBackgroundColor: '#256abf', maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' };
        const axis = (extra = {}) => ({ grid: { color: GRID, drawTicks: false }, border: { display: false }, ...extra });
        const plain = { grid: { display: false }, border: { color: GRID } };

        // 매출 추이: 세로 막대
        new Chart(this.$refs.trend, {
            type: 'bar',
            data: { labels: data.trend.map((t) => t.label), datasets: [{ ...bar, data: data.trend.map((t) => t.value) }] },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: {
                    title: (items) => data.trend[items[0].dataIndex].key,
                    label: (c) => `${won(c.raw)} · 완료 ${data.trend[c.dataIndex].count}건`,
                } } },
                scales: { x: { ...plain, ticks: { autoSkip: true, maxRotation: 0 } }, y: axis({ beginAtZero: true, ticks: { callback: man, padding: 8 } }) },
            },
        });

        // 가동률: 가로 막대 0~100%
        new Chart(this.$refs.resources, {
            type: 'bar',
            data: { labels: data.resources.map((r) => r.name), datasets: [{ ...bar, data: data.resources.map((r) => r.utilization ?? 0) }] },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: {
                    label: (c) => {
                        const r = data.resources[c.dataIndex];
                        return r.utilization === null ? '근무 시간 없음' : `${r.utilization}% · ${r.busy_hours} / ${r.open_hours}시간`;
                    },
                } } },
                scales: { x: axis({ min: 0, max: 100, ticks: { callback: (v) => `${v}%`, stepSize: 25 } }), y: plain },
            },
        });

        // 상품별 매출: 가로 막대
        if (this.$refs.services) {
            new Chart(this.$refs.services, {
                type: 'bar',
                data: { labels: data.services.map((s) => s.name), datasets: [{ ...bar, data: data.services.map((s) => s.revenue) }] },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: {
                        label: (c) => `${won(c.raw)} · ${data.services[c.dataIndex].share}% · ${data.services[c.dataIndex].count}건`,
                    } } },
                    scales: { x: axis({ beginAtZero: true, ticks: { callback: man } }), y: plain },
                },
            });
        }
    },
});
