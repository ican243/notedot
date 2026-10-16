<?php

namespace App\Services\Messaging;

use App\Contracts\MessageSender;

// 실제로 보내지 않는다 (솔라피 키가 없을 때·데모). 발송 기록(notification_logs)만 남는다
class LogSender implements MessageSender
{
    public function channelFor(string $type): string
    {
        return 'log';
    }

    public function send(string $to, string $text, string $type): SendResult
    {
        return new SendResult(true, 'log-'.bin2hex(random_bytes(6)));
    }
}
