<?php

use App\Http\Controllers\Shop\BookingController;
use App\Http\Controllers\Shop\HomeController;
use Illuminate\Support\Facades\Route;

// 고객 주소표 — bootstrap/app.php 에서 /s/{shop:slug} 접두어, shop. 이름표를 붙여 불러온다
// {shop:slug} : 주소의 slug 로 Shop 모델을 자동으로 찾아 컨트롤러에 넘겨준다 (없으면 404)

Route::get('/', [HomeController::class, 'index'])->name('home');

// 예약 흐름 (로그인 없이 이름·휴대폰 번호로). 저장은 1분에 10번까지 (같은 IP)
Route::get('booking/slots', [BookingController::class, 'slots'])->name('booking.slots');
Route::get('booking/sessions', [BookingController::class, 'sessions'])->name('booking.sessions');
Route::post('booking', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('booking.store');
Route::get('booking/done', [BookingController::class, 'done'])->name('booking.done');
