<?php

use Illuminate\Support\Facades\Schedule;

// 예약 작업표 — 서버 cron 이 1분마다 php artisan schedule:run 을 부르면, 시각이 맞는 작업을 실행한다

// 데모 모드면 매일 새벽 데모 데이터 초기화
Schedule::command('demo:reset')
    ->dailyAt(config('demo.reset_at'))
    ->when(fn () => config('demo.enabled'))
    ->withoutOverlapping();

// 보증금 결제 대기 시간이 지난 예약 자동 취소 (자리 풀기)
Schedule::command('payments:expire')
    ->everyMinute()
    ->withoutOverlapping();

// 내일 예약 리마인드 (매장별 리마인드 시각이 지나면, 예약마다 한 번만)
Schedule::command('notifications:remind')
    ->everyTenMinutes()
    ->withoutOverlapping();
