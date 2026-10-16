<?php

namespace App\Services\Messaging;

// 발송 결과
final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}
}
