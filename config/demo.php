<?php

use Database\Seeders\DemoShopSeeder;

// 공개 데모 운영 설정: 위험한 변경 막기 + 매일 데이터 초기화 + 로그인 화면에 데모 계정 표시
return [

    // .env 의 DEMO_MODE=true 일 때만 켜진다 (실제 운영 매장에서는 false)
    'enabled' => (bool) env('DEMO_MODE', false),

    // 스케줄러가 demo:reset 을 실행하는 시각 (Asia/Seoul)
    'reset_at' => env('DEMO_RESET_AT', '04:00'),

    'password' => DemoShopSeeder::PASSWORD,

    'accounts' => [
        ['email' => 'owner@notedot.test', 'label' => '미용실 · 대표'],
        ['email' => 'staff@notedot.test', 'label' => '미용실 · 직원'],
        ['email' => 'pilates@notedot.test', 'label' => '필라테스 · 대표'],
        ['email' => 'studyroom@notedot.test', 'label' => '스터디룸 · 대표'],
    ],
];
