<?php

// 입력값 검사 오류 문구 (한국어). :attribute 는 아래 attributes 의 이름으로 바뀐다
return [
    'after' => ':attribute은(는) :date 이후여야 합니다.',
    'after_or_equal' => ':attribute은(는) :date 이후 날짜여야 합니다.',
    'array' => ':attribute 형식이 올바르지 않습니다.',
    'between' => [
        'numeric' => ':attribute은(는) :min ~ :max 사이여야 합니다.',
    ],
    'boolean' => ':attribute 값이 올바르지 않습니다.',
    'date' => ':attribute은(는) 올바른 날짜가 아닙니다.',
    'date_format' => ':attribute은(는) :format 형식이어야 합니다.',
    'distinct' => ':attribute 값이 겹칩니다.',
    'email' => ':attribute 형식이 올바르지 않습니다.',
    'exists' => '선택한 :attribute이(가) 올바르지 않습니다.',
    'in' => '선택한 :attribute이(가) 올바르지 않습니다.',
    'integer' => ':attribute은(는) 숫자여야 합니다.',
    'lte' => [
        'numeric' => ':attribute은(는) :value 이하여야 합니다.',
    ],
    'max' => [
        'numeric' => ':attribute은(는) :max 이하여야 합니다.',
        'string' => ':attribute은(는) :max자 이하여야 합니다.',
        'array' => ':attribute은(는) :max개 이하여야 합니다.',
    ],
    'min' => [
        'numeric' => ':attribute은(는) :min 이상이어야 합니다.',
        'string' => ':attribute은(는) :min자 이상이어야 합니다.',
    ],
    'not_in' => ':attribute 값이 올바르지 않습니다.',
    'regex' => ':attribute 형식이 올바르지 않습니다.',
    'required' => ':attribute을(를) 입력하세요.',
    'required_if' => ':attribute을(를) 입력하세요.',
    'required_with' => ':attribute을(를) 입력하세요.',
    'string' => ':attribute은(는) 문자여야 합니다.',
    'unique' => '이미 사용 중인 :attribute입니다.',

    'attributes' => [
        'name' => '이름',
        'distinct' => ':attribute 값이 겹칩니다.',
        'email' => '이메일',
        'password' => '비밀번호',
        'role' => '권한',
        'industry' => '업종',
        'slot_unit' => '예약 단위',
        'theme' => '테마',
        'resource_id' => '연결 대상',
        'type' => '종류',
        'category' => '분류',
        'booking_type' => '예약 방식',
        'date' => '날짜',
        'memo' => '메모',
        'point_rate' => '포인트 적립률',
        'policy.*.hours' => '취소 규정 시간',
        'notify.reminder_at' => '리마인드 시각',
        'policy.*.percent' => '환불 비율',
    ],
];
