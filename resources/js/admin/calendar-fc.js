// FullCalendar 로 캘린더 그리기. 사용자가 무엇을 했는지(빈 칸 선택, 예약 클릭, 드래그)는 page 쪽 함수로 넘긴다
// - 주간: 날짜 7칸. 담당자 필터로 고른 사람들의 예약이 한 칸 안에 나란히
// - 일간: 담당자마다 한 칸. 무료 FullCalendar 에는 담당자 칸이 없어서, i번째 담당자를 "그날 + i일" 칸에 그리고
//         칸 머리글을 담당자 이름으로 바꾼다. 서버와 주고받을 때는 언제나 진짜 날짜로 되돌린다
import { Calendar } from '@fullcalendar/core';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import koLocale from '@fullcalendar/core/locales/ko';

const HM = { hour: '2-digit', minute: '2-digit', hour12: false };
const DAY = 86400000;

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n, d.getHours(), d.getMinutes());
const dayIndex = (d, base) => Math.round((new Date(d.getFullYear(), d.getMonth(), d.getDate()) - base) / DAY);
// "2026-10-19T10:00:00" 의 날짜만 n일 옮기기
const shiftStr = (s, n) => ymd(addDays(new Date(`${s.slice(0, 10)}T00:00:00`), n)) + s.slice(10);
// 가짜 칸 날짜(d)의 시각을 진짜 날짜(base)에 붙이기
const onDate = (base, d) => new Date(base.getFullYear(), base.getMonth(), base.getDate(), d.getHours(), d.getMinutes());

// 캘린더 판: 보기(day/week)·날짜·담당자가 바뀌면 show() 로 다시 그린다
export function mountCalendar(el, page) {
    const cfg = page.config;
    let calendar = null;

    function common() {
        const unit = { minutes: cfg.slotUnit };
        return {
            plugins: [timeGridPlugin, interactionPlugin],
            locale: koLocale,
            headerToolbar: false,          // 이동·보기 버튼은 화면(calendar.blade.php)에 따로 있다
            firstDay: 1,
            allDaySlot: false,
            height: 'auto',
            slotDuration: unit,
            snapDuration: unit,
            slotLabelInterval: { hours: 1 },
            slotMinTime: cfg.slotMin,
            slotMaxTime: cfg.slotMax,
            slotLabelFormat: HM,
            eventTimeFormat: HM,
            selectable: true,
            editable: true,
            eventDurationEditable: false,   // 길이는 상품 시간으로 정해지므로 드래그로 늘리지 않음
            slotEventOverlap: false,        // 같은 시간 예약은 포개지 않고 나란히
            eventMaxStack: 3,               // 한 칸에 4개 이상이면 "+N개"로 접고 누르면 펼침
            moreLinkContent: (arg) => `+${arg.num}개`,
            eventClick: (info) => page.openEvent(info.event),
            eventContent: renderEvent,
            eventDidMount: decorate,
        };
    }

    // 주간: 진짜 날짜 그대로
    function weekOptions(date) {
        return {
            initialView: 'timeGridWeek',
            initialDate: date,
            nowIndicator: true,
            businessHours: cfg.businessHours,
            events: (info, success, failure) => page.fetchEvents(info).then(success).catch(failure),
            select: (info) => { calendar.unselect(); page.openCreate(info.start, info.end); },
            eventDrop: (info) => page.moveEvent(info, info.event.startStr),
        };
    }

    // 일간(담당자 칸): i번째 담당자 = date + i일 칸
    function staffOptions(date, ids) {
        const resources = ids.map((id) => cfg.resources.find((r) => r.id === id)).filter(Boolean);
        const weekday = date.getDay();
        // 영업시간은 그날 요일 것을 모든 칸에 똑같이
        const hours = cfg.businessHours.filter((h) => h.daysOfWeek.includes(weekday))
            .map((h) => ({ ...h, daysOfWeek: [0, 1, 2, 3, 4, 5, 6] }));

        return {
            initialView: 'staffDay',
            initialDate: date,
            views: { staffDay: { type: 'timeGrid', duration: { days: Math.max(resources.length, 1) } } },
            nowIndicator: false,           // 가짜 날짜 칸이라 "지금" 선이 엉뚱한 칸에 생긴다
            businessHours: hours.length ? hours : false,
            dayHeaderContent: (arg) => {
                const r = resources[dayIndex(arg.date, date)];
                const box = document.createElement('div');
                box.className = 'cal-head';
                if (r) {
                    const dot = document.createElement('span');
                    dot.className = 'cal-dot';
                    dot.style.background = r.color;
                    const name = document.createElement('span');
                    name.textContent = r.name;
                    box.append(dot, name);
                }
                return { domNodes: [box] };
            },
            events: (info, success, failure) => page.fetchEvents({ startStr: ymd(date), endStr: ymd(addDays(date, 1)) })
                .then((list) => success(list.flatMap((e) => {
                    const i = resources.findIndex((r) => r.id === e.extendedProps.resourceId);
                    return i < 0 ? [] : [{ ...e, start: shiftStr(e.start, i), end: shiftStr(e.end, i) }];
                })))
                .catch(failure),
            select: (info) => {
                calendar.unselect();
                const r = resources[dayIndex(info.start, date)];
                page.openCreate(onDate(date, info.start), onDate(date, info.end), r?.id ?? null);
            },
            // 다른 담당자 칸으로는 못 옮긴다 (담당 변경은 예약 창에서)
            eventAllow: (drop, ev) => dayIndex(drop.start, date) === dayIndex(ev.start, date),
            eventDrop: (info) => {
                const s = onDate(date, info.event.start);
                page.moveEvent(info, `${ymd(s)}T${pad(s.getHours())}:${pad(s.getMinutes())}:00`);
            },
        };
    }

    return {
        // view: 'day' | 'week', date: 기준 날짜(자정), ids: 보이는 담당자
        show(view, date, ids) {
            calendar?.destroy();
            const staff = view === 'day';
            el.classList.toggle('cal-staff', staff);
            // 칸이 너무 좁아지지 않게: 담당자 칸 150px, 요일 칸 100px + 시간 줄. 넘치면 캘린더 상자 안에서만 옆으로 넘긴다
            el.style.minWidth = `${56 + (staff ? Math.max(ids.length, 1) * 150 : 700)}px`;
            // 한 시간 = 72px 가 되도록 칸 높이를 예약 단위에 맞춘다 (60분 단위 매장에서 50분 수업이 16px 로 눌리지 않게)
            el.style.setProperty('--slot-h', `${Math.max(18, Math.round(72 * cfg.slotUnit / 60))}px`);
            calendar = new Calendar(el, { ...common(), ...(staff ? staffOptions(date, ids) : weekOptions(date)) });
            calendar.render();
        },
        refetchEvents() {
            calendar?.refetchEvents();
        },
    };
}

// 블록에 담당 색을 넘기고, 마우스를 올리면 전체 내용을 보여 준다 (좁은 블록에서 줄인 내용 확인용)
function decorate(info) {
    const p = info.event.extendedProps;
    info.el.style.setProperty('--rc', p.color || '#8a929e');
    info.el.title = [info.timeText, info.event.title, p.kind === 'session' ? `${p.taken}/${p.capacity}명` : p.services, statusText(p)]
        .filter(Boolean).join(' · ');
}

function statusText(p) {
    if (p.kind === 'session') return p.status === 'canceled' ? '취소' : (p.taken >= p.capacity ? '마감' : '');
    return p.status === 'confirmed' ? '' : p.statusLabel;
}

// 예약·일정 블록 안 내용: 시간 · 상태 배지 / 고객 이름 / 서비스. 고객 이름 등은 textContent 로 넣어서 HTML 로 해석되지 않게 한다 (XSS 방지)
function renderEvent(arg) {
    const p = arg.event.extendedProps;
    const box = document.createElement('div');
    box.className = 'ev-box';
    const inner = document.createElement('div');
    inner.className = 'ev-in';
    box.appendChild(inner);

    const add = (parent, text, cls) => {
        const d = document.createElement('span');
        d.className = cls;
        d.textContent = text;
        parent.appendChild(d);
    };

    // 첫 줄(시간 + 배지)은 한 줄 고정: 좁으면 시간이 줄어들고 배지는 그대로 보인다
    const head = document.createElement('div');
    head.className = 'ev-head';
    inner.appendChild(head);
    add(head, arg.timeText, 'ev-time');
    const badge = statusText(p);
    if (badge) {
        const key = p.kind === 'session' ? (p.status === 'canceled' ? 'canceled' : 'full') : p.status;
        add(head, badge, `ev-badge ev-badge-${key}`);
    }
    add(inner, arg.event.title, 'ev-title');
    add(inner, p.kind === 'session' ? `그룹 · ${p.taken}/${p.capacity}명` : p.services, 'ev-sub');

    return { domNodes: [box] };
}
