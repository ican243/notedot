<?php

namespace App\Services;

use App\Contracts\MessageSender;
use App\Jobs\SendNotification;
use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// 알림: 예약 확정·취소, 전날 리마인드. 기록(notification_logs)을 먼저 만들고 큐로 보낸다
// → 예약 저장이 문자 발송 때문에 느려지지 않고, 무엇을 언제 보냈는지 전부 남는다
class NotificationService
{
    private const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];

    public function __construct(private MessageSender $sender) {}

    // 예약이 바뀐 사건 → 보낼 알림 고르기 (예약 확정 / 예약 취소)
    public function forReservation(Reservation $reservation, string $action): void
    {
        $type = match (true) {
            in_array($action, ['created', 'status'], true) && $reservation->status === 'confirmed' => 'confirmed',
            $action === 'status' && $reservation->status === 'canceled' => 'canceled',
            default => null,
        };

        if ($type) {
            $this->queueFor($reservation, $type);
        }
    }

    // 알림 기록을 만들고 큐에 넣는다. 보내지 않을 이유가 있으면 null
    public function queueFor(Reservation $reservation, string $type, ?string $dedupeKey = null): ?NotificationLog
    {
        $reservation->loadMissing(['shop', 'customer']);
        $shop = $reservation->shop;

        if (! config('notification.enabled')
            || ! ($shop->notifySettings()[$type] ?? false)
            || $reservation->channel === 'walk_in'          // 매장에 와 있는 손님
            || blank($reservation->customer?->phone)) {
            return null;
        }

        try {
            $log = $shop->notificationLogs()->create([
                'customer_id' => $reservation->customer_id,
                'reservation_id' => $reservation->id,
                'type' => $type,
                'channel' => $this->sender->channelFor($type),
                'to_phone' => $reservation->customer->phone,
                'message' => $this->render($type, $reservation),
                'status' => 'pending',
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;   // 이미 보낸 알림 (리마인드 중복 방지)
        }

        SendNotification::dispatch($log->id)->afterCommit();

        return $log;
    }

    // 실제 발송 (큐 일꾼이 부른다). 대기 중인 것만, 행을 잠가서 두 번 보내지 않는다
    public function deliver(NotificationLog $log): NotificationLog
    {
        return DB::transaction(function () use ($log) {
            $log = NotificationLog::whereKey($log->id)->lockForUpdate()->firstOrFail();
            if ($log->status !== 'pending') {
                return $log;
            }

            $result = $this->sender->send($log->to_phone, $log->message, $log->type);
            $log->update($result->ok
                ? ['status' => 'sent', 'provider_message_id' => $result->messageId, 'error' => null, 'sent_at' => now()]
                : ['status' => 'failed', 'error' => $result->error]);

            return $log;
        });
    }

    // 실패한 알림 다시 보내기
    public function retry(NotificationLog $log): void
    {
        $log->update(['status' => 'pending', 'error' => null, 'channel' => $this->sender->channelFor($log->type)]);
        SendNotification::dispatch($log->id);
    }

    // 리마인드 시각이 지난 매장마다, 내일 예약에 한 번씩 (스케줄러가 10분마다). 큐에 넣은 개수
    public function sendReminders(?CarbonInterface $now = null): int
    {
        $now = CarbonImmutable::instance($now ?? now());
        $tomorrow = $now->addDay()->startOfDay();
        $count = 0;

        foreach (Shop::all() as $shop) {
            $settings = $shop->notifySettings();
            if (! $settings['reminder'] || $now->format('H:i') < $settings['reminder_at']) {
                continue;
            }

            $shop->reservations()
                ->where('status', 'confirmed')
                ->whereBetween('start_at', [$tomorrow, $tomorrow->endOfDay()])
                ->where('created_at', '<=', $now->subMinutes(config('notification.reminder_skip_recent_minutes')))
                ->with(['shop', 'customer', 'items', 'resource'])
                ->get()
                ->each(function (Reservation $r) use (&$count) {
                    $key = 'reminder:'.$r->id.':'.$r->start_at->format('Ymd');
                    $count += $this->queueFor($r, 'reminder', $key) ? 1 : 0;
                });
        }

        return $count;
    }

    // 알림 문구 (업종 용어는 매장 설정에서)
    public function render(string $type, Reservation $reservation): string
    {
        $reservation->loadMissing(['shop', 'customer', 'items', 'resource']);
        $shop = $reservation->shop;
        $start = $reservation->start_at;
        $when = $start->format('n/j').'('.self::WEEKDAYS[$start->dayOfWeek].') '.$start->format('H:i');
        $what = $reservation->items->pluck('service_name')->implode(', ');
        $head = "[{$shop->name}] {$reservation->customer->name}님, ";
        $who = term('resource', $shop).': '.$reservation->resource->name;

        return match ($type) {
            'confirmed' => $head."{$when} {$what} 예약이 확정되었습니다.\n{$who}\n변경·취소는 매장으로 연락해 주세요.",
            'reminder' => $head."내일 {$when} {$what} 예약이 있습니다.\n{$who}\n늦거나 못 오시면 미리 알려 주세요.",
            'canceled' => $head."{$when} {$what} 예약이 취소되었습니다."
                .($reservation->cancel_reason ? "\n사유: {$reservation->cancel_reason}" : '')
                .($reservation->payment?->paid_at ? "\n보증금은 취소 규정에 따라 환불됩니다." : ''),
        };
    }
}
