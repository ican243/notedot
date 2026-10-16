<?php

namespace App\Services\Messaging;

use App\Contracts\MessageSender;
use Illuminate\Support\Facades\Http;
use Throwable;

// 솔라피 발송 (POST /messages/v4/send)
// 인증 헤더: HMAC-SHA256 apiKey=..., date=..., salt=..., signature=hex(hmac_sha256(date + salt, apiSecret))
// 알림톡 템플릿이 있으면 알림톡(실패하면 솔라피가 문자로 대신 보냄), 없으면 문자(긴 글은 자동으로 LMS)
class SolapiSender implements MessageSender
{
    public function __construct(private array $cfg) {}

    public function channelFor(string $type): string
    {
        return $this->templateFor($type) ? 'alimtalk' : 'sms';
    }

    public function send(string $to, string $text, string $type): SendResult
    {
        $message = ['to' => $to, 'from' => $this->cfg['sender'], 'text' => $text];
        if ($template = $this->templateFor($type)) {
            $message['kakaoOptions'] = ['pfId' => $this->cfg['pf_id'], 'templateId' => $template, 'disableSms' => false];
        }

        try {
            $res = Http::baseUrl(rtrim($this->cfg['api_url'], '/'))
                ->withHeaders(['Authorization' => $this->authHeader()])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->post('/messages/v4/send', ['message' => $message]);
        } catch (Throwable $e) {
            return new SendResult(false, error: '연결 실패: '.$e->getMessage());
        }

        // 접수 성공: statusCode 2000
        if ($res->successful() && (string) $res->json('statusCode') === '2000') {
            return new SendResult(true, $res->json('messageId'));
        }

        return new SendResult(false, error: mb_substr(($res->json('errorMessage') ?? $res->json('statusMessage') ?? 'HTTP '.$res->status()), 0, 250));
    }

    public function authHeader(): string
    {
        $date = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $salt = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $date.$salt, $this->cfg['api_secret']);

        return "HMAC-SHA256 apiKey={$this->cfg['api_key']}, date={$date}, salt={$salt}, signature={$signature}";
    }

    private function templateFor(string $type): ?string
    {
        return filled($this->cfg['pf_id']) ? ($this->cfg['templates'][$type] ?? null) ?: null : null;
    }
}
