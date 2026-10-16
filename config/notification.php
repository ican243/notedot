<?php

// 알림(문자·알림톡) 설정
// driver: log = 실제로 보내지 않고 기록만 / solapi = 솔라피로 발송 (키·발신번호가 모두 있어야 켜짐)
return [
    'driver' => env('NOTIFICATION_DRIVER', 'log'),

    // false 면 알림을 만들지 않는다 (데모 데이터를 넣을 때)
    'enabled' => true,

    // 매장이 따로 정하지 않았을 때
    'defaults' => ['confirmed' => true, 'canceled' => true, 'reminder' => true, 'reminder_at' => '18:00'],

    // 예약한 지 이 시간(분)이 안 됐으면 리마인드를 보내지 않는다 (방금 확정 문자를 받았으므로)
    'reminder_skip_recent_minutes' => 180,

    'solapi' => [
        'api_url' => env('SOLAPI_API_URL', 'https://api.solapi.com'),
        'api_key' => env('SOLAPI_API_KEY'),
        'api_secret' => env('SOLAPI_API_SECRET'),
        'sender' => env('SOLAPI_SENDER'),            // 등록된 발신번호
        'pf_id' => env('SOLAPI_KAKAO_PF_ID'),         // 카카오 채널 (비우면 문자로만)
        // 알림톡 템플릿 ID (검수 받은 문구와 같아야 한다. 비우면 그 알림은 문자로)
        'templates' => [
            'confirmed' => env('SOLAPI_TEMPLATE_CONFIRMED'),
            'reminder' => env('SOLAPI_TEMPLATE_REMINDER'),
            'canceled' => env('SOLAPI_TEMPLATE_CANCELED'),
        ],
    ],
];
