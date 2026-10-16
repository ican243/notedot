<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// 큐에서 알림 한 건 보내기 (연결 오류처럼 예외가 나면 10초·60초 뒤 다시, 최대 3번)
class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public int $logId) {}

    public function handle(NotificationService $notifications): void
    {
        $log = NotificationLog::find($this->logId);
        if ($log) {
            $notifications->deliver($log);
        }
    }
}
