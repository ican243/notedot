<?php

// 실시간 알림 설정
// driver: node = Node 서버로 보내고 화면은 Socket.io 로 받는다 / poll = 보내지 않고 화면이 몇 초마다 다시 불러온다 (Node 를 못 쓰는 곳)
return [
    'driver' => env('REALTIME_DRIVER', 'poll'),

    // Laravel → Node (서버 안쪽 주소). 공유 시크릿 헤더로 확인한다
    'server_url' => env('REALTIME_SERVER_URL', 'http://127.0.0.1:3100'),
    'secret' => env('REALTIME_SECRET'),

    // 화면 → Node 접속용 출입증(JWT) 서명 키와 유효 시간(초). 접속할 때마다 새로 받는다
    'jwt_secret' => env('REALTIME_JWT_SECRET'),
    'token_ttl' => 60,

    // 화면이 접속할 주소 경로 (nginx 가 Node 로 넘겨준다). 비우면 APP_URL 경로 + /socket.io
    'socket_path' => env('REALTIME_SOCKET_PATH'),

    // poll 방식이거나 Node 연결이 끊겼을 때 다시 불러오는 간격(초)
    'poll_seconds' => 10,
];
