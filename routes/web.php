<?php

use Illuminate\Support\Facades\Route;

// 서비스 첫 주소 → 데모 안내 화면이 생기기 전까지 관리자 로그인으로
Route::redirect('/', 'admin/login');
