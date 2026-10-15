import Alpine from 'alpinejs';
import calendarPage from './admin/calendar-page';

// 화면에서 x-data="calendarPage(...)" 로 쓰는 부품 등록
Alpine.data('calendarPage', calendarPage);

window.Alpine = Alpine;
Alpine.start();
