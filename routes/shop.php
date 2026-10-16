<?php

use App\Http\Controllers\Shop\BookingController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PaymentController;
use App\Http\Controllers\Shop\RealtimeTokenController;
use Illuminate\Support\Facades\Route;

// 고객 주소표 — bootstrap/app.php 에서 /s/{shop:slug} 접두어, shop. 이름표를 붙여 불러온다
// {shop:slug} : 주소의 slug 로 Shop 모델을 자동으로 찾아 컨트롤러에 넘겨준다 (없으면 404)

Route::get('/', [HomeController::class, 'index'])->name('home');

// 예약 흐름 (로그인 없이 이름·휴대폰 번호로). 저장은 1분에 10번까지 (같은 IP)
Route::get('booking/slots', [BookingController::class, 'slots'])->name('booking.slots');
Route::get('booking/sessions', [BookingController::class, 'sessions'])->name('booking.sessions');
Route::post('booking', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('booking.store');
Route::get('booking/done', [BookingController::class, 'done'])->name('booking.done');

// 보증금 결제 (이 브라우저에서 방금 한 예약만)
Route::get('pay/{paymentKey}', [PaymentController::class, 'show'])->name('pay.show');
Route::get('pay/{paymentKey}/return', [PaymentController::class, 'return'])->name('pay.return');
Route::middleware('throttle:20,1')->group(function () {
    Route::post('pay/{paymentKey}/complete', [PaymentController::class, 'complete'])->name('pay.complete');
    Route::post('pay/{paymentKey}/demo', [PaymentController::class, 'demo'])->name('pay.demo');
});

// 실시간 서버 접속 출입증 (시간이 바뀌면 화면에 바로 "마감" 표시)
Route::get('realtime/token', RealtimeTokenController::class)->middleware('throttle:30,1')->name('realtime.token');
