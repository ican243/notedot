<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

// 내일 예약 리마인드 (스케줄러가 10분마다. 매장별 리마인드 시각이 지나면 한 번씩만)
class SendReminders extends Command
{
    protected $signature = 'notifications:remind';

    protected $description = '리마인드 시각이 지난 매장의 내일 예약에 알림을 보냅니다';

    public function handle(NotificationService $notifications): int
    {
        $count = $notifications->sendReminders();
        if ($count) {
            $this->info("리마인드 {$count}건");
        }

        return self::SUCCESS;
    }
}
