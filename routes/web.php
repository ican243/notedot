<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// 서비스 첫 주소 → 데모 안내 화면이 생기기 전까지 관리자 로그인으로
Route::redirect('/', 'admin/login');

// 결제 대행사(PG) 웹훅 — CSRF 검사 제외(bootstrap/app.php), 서명으로 확인
Route::post('payments/webhook', PaymentWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('payments.webhook');
