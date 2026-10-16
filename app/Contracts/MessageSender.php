<?php

namespace App\Contracts;

use App\Services\Messaging\SendResult;

// 문자·알림톡 보내기. 솔라피(SolapiSender) 또는 기록만(LogSender)
interface MessageSender
{
    // 이 알림이 어떤 길로 나갈지 (alimtalk | sms | log)
    public function channelFor(string $type): string;

    public function send(string $to, string $text, string $type): SendResult;
}
