import Alpine from 'alpinejs';
import calendarPage from './admin/calendar-page';
import bookingPage from './shop/booking-page';
import payPage from './shop/pay-page';

// 화면에서 x-data="calendarPage(...)" 처럼 쓰는 부품 등록
Alpine.data('calendarPage', calendarPage);   // 관리자 예약 캘린더
Alpine.data('bookingPage', bookingPage);     // 고객 예약 화면
Alpine.data('payPage', payPage);             // 보증금 결제 화면

window.Alpine = Alpine;
Alpine.start();
