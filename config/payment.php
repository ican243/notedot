<?php

// 결제 설정
// driver: demo = 가짜 결제창(실제 돈이 나가지 않음) / portone = 포트원 V2 (키가 모두 있어야 켜짐)
return [
    'driver' => env('PAYMENT_DRIVER', 'demo'),

    // 결제를 기다려 주는 시간(분). 지나면 예약을 자동 취소해서 자리를 푼다
    'hold_minutes' => 15,

    // 매장이 취소 규정을 정하지 않았을 때: 24시간 전까지 100%, 3시간 전까지 50%, 그 뒤 0%
    'default_cancel_policy' => ['tiers' => [['hours' => 24, 'percent' => 100], ['hours' => 3, 'percent' => 50]]],

    'portone' => [
        'api_url' => env('PORTONE_API_URL', 'https://api.portone.io'),
        'store_id' => env('PORTONE_STORE_ID'),
        'channel_key' => env('PORTONE_CHANNEL_KEY'),
        'api_secret' => env('PORTONE_API_SECRET'),
        'webhook_secret' => env('PORTONE_WEBHOOK_SECRET'),
    ],
];
