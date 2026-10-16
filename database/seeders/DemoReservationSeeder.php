<?php

namespace Database\Seeders;

use App\Models\ClassSession;
use App\Models\Resource;
use App\Models\Shop;
use App\Services\ReservationService;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

// 데모 매장마다 고객 14명 + 지난 5일 ~ 앞으로 9일 예약을 근무표 안에서 채운다 + 그룹 일정 신청자
// 실제 화면과 같은 ReservationService 로 만들기 때문에 겹치는 예약은 생기지 않는다
class DemoReservationSeeder extends Seeder
{
    private const NAMES = ['김민준', '이서연', '박지호', '최수아', '정예준', '강하윤', '조도윤', '윤지우', '장시우', '임서윤', '한주원', '오하은', '서지안', '신유나'];

    public function __construct(
        private ReservationService $reservations,
        private ScheduleService $schedules,
    ) {}

    public function run(): void
    {
        Shop::whereIn('slug', ['demo-hair', 'demo-pilates', 'demo-studyroom'])->get()->each(fn (Shop $shop) => $this->fill($shop));
    }

    private function fill(Shop $shop): void
    {
        mt_srand(crc32($shop->slug));   // 매번 같은 결과가 나오도록 난수 고정
        $owner = $shop->staffs()->where('role', 'owner')->first();
        $customers = collect(self::NAMES)->map(fn ($name, $i) => $shop->customers()->create([
            'name' => $name,
            'phone' => sprintf('0109%03d%04d', $shop->id, $i + 1),
            'privacy_agreed_at' => now(),
        ]));

        $today = CarbonImmutable::today();
        foreach (range(-5, 9) as $offset) {
            $date = $today->addDays($offset);
            foreach ($shop->resources()->with('services')->get() as $resource) {
                $this->fillDay($shop, $owner, $resource, $date, $customers);
            }
        }

        $shop->classSessions()->orderBy('start_at')->get()->each(fn (ClassSession $s) => $this->fillSession($s, $owner, $customers));
    }

    // 그룹 일정마다 2명 ~ 정원까지 신청 (고객이 겹치지 않게)
    private function fillSession(ClassSession $session, $owner, $customers): void
    {
        $picked = $customers->sortBy(fn () => mt_rand())->take(mt_rand(2, $session->capacity));
        $past = $session->start_at->isPast();

        foreach ($picked as $customer) {
            $roll = mt_rand(1, 100);
            $status = $past
                ? ($roll <= 85 ? 'completed' : ($roll <= 93 ? 'no_show' : 'canceled'))
                : ($roll <= 90 ? 'confirmed' : ($roll <= 95 ? 'pending' : 'canceled'));

            $reservation = $this->reservations->bookSession($session, $owner, [
                'customer_id' => $customer->id,
                'channel' => ['phone', 'online', 'online', 'walk_in'][mt_rand(0, 3)],
                'status' => in_array($status, ['completed', 'confirmed', 'pending']) ? $status : 'confirmed',
            ]);
            if (in_array($status, ['no_show', 'canceled'])) {
                $this->reservations->changeStatus($reservation, $owner, $status, $status === 'canceled' ? '고객 요청' : null);
            }
        }
    }

    private function fillDay(Shop $shop, $owner, Resource $resource, CarbonImmutable $date, $customers): void
    {
        $day = $this->schedules->weekly($resource)[$date->dayOfWeek];
        $services = $resource->services->where('booking_type', '!=', 'group')->values();
        if (! $day['on'] || $services->isEmpty()) {
            return;
        }

        // 근무 시간을 쉬는 시간 앞/뒤 두 구간으로
        $segments = $day['break_start']
            ? [[$day['start'], $day['break_start']], [$day['break_end'], $day['end']]]
            : [[$day['start'], $day['end']]];
        $unit = $shop->slot_unit;

        foreach ($segments as [$from, $to]) {
            $t = $date->setTimeFromTimeString($from)->addMinutes(mt_rand(0, 2) * $unit);
            $end = $date->setTimeFromTimeString($to);

            while (true) {
                $service = $services[mt_rand(0, $services->count() - 1)];
                if ($t->addMinutes($service->duration_min) > $end) {
                    break;
                }
                if (mt_rand(1, 100) > 55) {          // 45% 는 빈 시간으로 둔다
                    $t = $t->addMinutes($unit * mt_rand(1, 2));

                    continue;
                }

                if (! $this->book($shop, $owner, $resource, $service, $t, $customers)) {
                    $t = $t->addMinutes($unit);   // 그룹 일정과 겹치면 한 칸 뒤로

                    continue;
                }
                $next = $service->duration_min + $service->buffer_min;
                $t = $t->addMinutes((int) ceil($next / $unit) * $unit);
            }
        }
    }

    // 만들었으면 true, 그룹 일정 등과 겹쳐서 못 만들면 false
    private function book(Shop $shop, $owner, Resource $resource, $service, CarbonImmutable $start, $customers): bool
    {
        $past = $start->isPast();
        $roll = mt_rand(1, 100);
        $status = $past
            ? ($roll <= 85 ? 'completed' : ($roll <= 90 ? 'no_show' : 'canceled'))
            : ($roll <= 85 ? 'confirmed' : ($roll <= 95 ? 'pending' : 'canceled'));

        try {
            $reservation = $this->reservations->create($shop, $owner, [
                'customer_id' => $customers[mt_rand(0, $customers->count() - 1)]->id,
                'resource_id' => $resource->id,
                'service_ids' => [$service->id],
                'date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i'),
                'party_size' => $resource->type === 'space' ? mt_rand(2, min(4, $resource->max_party ?? 4)) : 1,
                'channel' => ['phone', 'phone', 'walk_in', 'online'][mt_rand(0, 3)],
                'status' => in_array($status, ['completed', 'confirmed', 'pending']) ? $status : 'confirmed',
            ]);
        } catch (ValidationException) {
            return false;
        }

        // 노쇼·취소는 실제 화면과 같은 방법(상태 변경)으로 → 노쇼 횟수도 올라간다
        if (in_array($status, ['no_show', 'canceled'])) {
            $this->reservations->changeStatus($reservation, $owner, $status, $status === 'canceled' ? '고객 요청' : null);
        }

        return true;
    }
}
