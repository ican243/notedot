import Alpine from 'alpinejs';
import calendarPage from './admin/calendar-page';
import statsPage from './admin/stats-page';
import bookingPage from './shop/booking-page';
import payPage from './shop/pay-page';
import seasonSwitch from './season-switch';

// 화면에서 x-data="calendarPage(...)" 처럼 쓰는 부품 등록
Alpine.data('calendarPage', calendarPage);   // 관리자 예약 캘린더
Alpine.data('statsPage', statsPage);         // 관리자 통계 차트
Alpine.data('bookingPage', bookingPage);     // 고객 예약 화면
Alpine.data('payPage', payPage);             // 보증금 결제 화면
Alpine.data('seasonSwitch', seasonSwitch);   // 데모용 계절 전환

window.Alpine = Alpine;
Alpine.start();
