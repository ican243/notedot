<?php

// 업종 프리셋 (용어 사전). 매장을 만들 때 고르고, 매장 설정에서 용어만 따로 바꿀 수 있다
//   labels        : 화면에 보일 이름 — term('resource') 가 여기서 찾는다
//   slot_unit     : 예약 시간 단위(분)
//   booking_types : 이 업종에서 쓸 수 있는 예약 방식
//   resource_type : 새 resource 를 만들 때 기본 종류 (person / space)
//   icon, accent  : 데모 안내 화면 업종 카드의 아이콘(x-icon 이름)과 강조색 (계절 무관 고정, 흰 바탕 대비 4.5:1 이상)

return [
    'beauty' => [
        'name' => '미용·뷰티',
        'labels' => ['resource' => '디자이너', 'service' => '시술', 'customer' => '고객'],
        'slot_unit' => 30,
        'booking_types' => ['one_to_one'],
        'resource_type' => 'person',
        'icon' => 'scissors',
        'accent' => '#b0436f',
    ],
    'fitness' => [
        'name' => '피트니스·필라테스',
        'labels' => ['resource' => '강사', 'service' => '수업', 'customer' => '회원'],
        'slot_unit' => 60,
        'booking_types' => ['one_to_one', 'group'],
        'resource_type' => 'person',
        'icon' => 'users',
        'accent' => '#2e7a58',
    ],
    'clinic' => [
        'name' => '병원·클리닉',
        'labels' => ['resource' => '의사', 'service' => '진료', 'customer' => '환자'],
        'slot_unit' => 15,
        'booking_types' => ['one_to_one'],
        'resource_type' => 'person',
        'icon' => 'stethoscope',
        'accent' => '#0f766e',
    ],
    'space' => [
        'name' => '공간 대여',
        'labels' => ['resource' => '공간', 'service' => '이용권', 'customer' => '이용자'],
        'slot_unit' => 60,
        'booking_types' => ['space'],
        'resource_type' => 'space',
        'icon' => 'door',
        'accent' => '#5a4bbf',
    ],
];
