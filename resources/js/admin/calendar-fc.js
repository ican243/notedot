// FullCalendar 로 캘린더 그리기. 사용자가 무엇을 했는지(빈 칸 선택, 예약 클릭, 드래그)는 page 쪽 함수로 넘긴다
import { Calendar } from '@fullcalendar/core';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import koLocale from '@fullcalendar/core/locales/ko';

const HM = { hour: '2-digit', minute: '2-digit', hour12: false };

export function mountCalendar(el, page) {
    const cfg = page.config;
    const unit = { minutes: cfg.slotUnit };

    const calendar = new Calendar(el, {
        plugins: [timeGridPlugin, interactionPlugin],
        locale: koLocale,
        initialView: window.matchMedia('(max-width: 768px)').matches ? 'timeGridDay' : 'timeGridWeek',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridDay,timeGridWeek' },
        firstDay: 1,
        allDaySlot: false,
        nowIndicator: true,
        height: 'auto',
        slotDuration: unit,
        snapDuration: unit,
        slotLabelInterval: { hours: 1 },
        slotMinTime: cfg.slotMin,
        slotMaxTime: cfg.slotMax,
        slotLabelFormat: HM,
        eventTimeFormat: HM,
        businessHours: cfg.businessHours,
        selectable: true,
        editable: true,
        eventDurationEditable: false,   // 길이는 상품 시간으로 정해지므로 드래그로 늘리지 않음

        events: (info, success, failure) => page.fetchEvents(info).then(success).catch(failure),
        select: (info) => {
            calendar.unselect();
            page.openCreate(info.start, info.end);
        },
        eventClick: (info) => page.openEvent(info.event),
        eventDrop: (info) => page.moveEvent(info),
        eventContent: renderEvent,
    });

    calendar.render();

    return calendar;
}

// 예약·일정 블록 안 내용. 고객 이름 등은 textContent 로 넣어서 HTML 로 해석되지 않게 한다 (XSS 방지)
function renderEvent(arg) {
    const p = arg.event.extendedProps;
    const wrap = document.createElement('div');
    wrap.className = 'ev-body';

    const line = (text, cls) => {
        const d = document.createElement('div');
        d.className = cls;
        d.textContent = text;
        wrap.appendChild(d);
    };

    line(arg.timeText + (p.status !== 'confirmed' ? ` · ${p.statusLabel}` : ''), 'ev-time');
    line(arg.event.title, 'ev-title');
    if (p.kind === 'session') {
        line(`${p.taken}/${p.capacity}명` + (p.taken >= p.capacity ? ' 마감' : ''), 'ev-sub');   // 그룹 일정: 신청 인원/정원
    } else {
        line(p.services, 'ev-sub');
    }

    return { domNodes: [wrap] };
}
