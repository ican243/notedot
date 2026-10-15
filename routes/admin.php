<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
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
});
