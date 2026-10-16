<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\ClassSessionController;
use App\Http\Controllers\Admin\CustomerSearchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ShopSettingController;
use App\Http\Controllers\Admin\StaffController;
use Illuminate\Support\Facades\Route;

// 관리자 주소표 — bootstrap/app.php 에서 /admin 접두어, admin. 이름표를 붙여 불러온다

Route::redirect('/', 'admin/dashboard');

// 로그인 안 한 사람만
Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.attempt');
});

// 로그인한 관리자만
Route::middleware('auth:admin')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 예약 캘린더 (대표·직원 모두. 직원은 자기 resource 만)
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::get('customers/search', CustomerSearchController::class)->name('customers.search');
    Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::put('reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
    Route::patch('reservations/{reservation}/move', [ReservationController::class, 'move'])->name('reservations.move');
    Route::patch('reservations/{reservation}/status', [ReservationController::class, 'status'])->name('reservations.status');

    // 그룹 일정 (정원제) — 일정 만들기·수정·이동·취소, 신청자 추가
    Route::post('class-sessions', [ClassSessionController::class, 'store'])->name('class-sessions.store');
    Route::get('class-sessions/{classSession}', [ClassSessionController::class, 'show'])->name('class-sessions.show');
    Route::put('class-sessions/{classSession}', [ClassSessionController::class, 'update'])->name('class-sessions.update');
    Route::patch('class-sessions/{classSession}/move', [ClassSessionController::class, 'move'])->name('class-sessions.move');
    Route::patch('class-sessions/{classSession}/cancel', [ClassSessionController::class, 'cancel'])->name('class-sessions.cancel');
    Route::post('class-sessions/{classSession}/reservations', [ClassSessionController::class, 'book'])->name('class-sessions.book');

    // 대표만
    Route::middleware('owner')->group(function () {
        Route::get('settings/shop', [ShopSettingController::class, 'edit'])->name('settings.shop');
        Route::put('settings/shop', [ShopSettingController::class, 'update'])->name('settings.shop.update');

        // resource: 주소 7개(목록·추가·저장·수정·갱신·삭제)를 한 줄로 — show 는 안 씀
        Route::resource('staffs', StaffController::class)->except('show')->middleware('demo');
        Route::resource('resources', ResourceController::class)->except('show');
        Route::resource('services', ServiceController::class)->except('show');

        Route::get('resources/{resource}/schedule', [ScheduleController::class, 'edit'])->name('schedules.edit');
        Route::put('resources/{resource}/schedule', [ScheduleController::class, 'updateWeekly'])->name('schedules.weekly');
        Route::post('resources/{resource}/schedule/exceptions', [ScheduleController::class, 'storeException'])->name('schedules.exceptions.store');
        Route::delete('resources/{resource}/schedule/exceptions/{schedule}', [ScheduleController::class, 'destroyException'])->name('schedules.exceptions.destroy');
    });
});
