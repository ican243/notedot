<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Resource;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// 통계: 매출(완료된 예약 이용 금액) 추이, 담당별 실적·가동률, 상품별 비중, 재방문율, 경로, 노쇼율
// 기간 [from, to] 은 날짜(포함). 모든 조회는 이 매장 안에서만
class StatsService
{
    public function __construct(
        private ScheduleService $schedules,
        private SlotService $slots,
    ) {}

    public const PRESETS = ['this_month' => '이번 달', 'last_month' => '지난달', 'last_30' => '최근 30일', 'this_year' => '올해', 'custom' => '직접 고르기'];

    /**
     * 화면에서 고른 기간 → [시작일, 종료일]. 잘못된 값이면 이번 달. 최대 2년
     *
     * @return array{0:CarbonImmutable, 1:CarbonImmutable, 2:string}
     */
    public function period(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::today();

        [$a, $b] = match ($preset) {
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$today->subDays(29), $today],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'custom' => [$this->date($from), $this->date($to)],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };

        if (! $a || ! $b || $a > $b || $a->diffInDays($b) > 731) {
            return [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'this_month'];
        }

        return [$a->startOfDay(), $b->startOfDay(), array_key_exists($preset, self::PRESETS) ? $preset : 'this_month'];
    }

    // 화면 한 장에 필요한 숫자 전부
    public function summary(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = (int) $from->diffInDays($to->startOfDay()) + 1;
        $prevFrom = $from->subDays($days);
        $prevTo = $from->subSecond();

        $revenue = $this->revenue($shop, $from, $to);
        $completed = $this->completed($shop, $from, $to);
        $noShows = $this->inPeriod($shop, $from, $to)->where('status', 'no_show')->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'kpi' => [
                'revenue' => $revenue,
                'revenue_prev' => $this->revenue($shop, $prevFrom, $prevTo),
                'visits' => $completed,
                'avg_ticket' => $completed ? intdiv($revenue, $completed) : 0,
                'no_show_rate' => $this->rate($noShows, $completed + $noShows),
                'deposit_net' => $this->depositNet($shop, $from, $to),
                'pass_sales' => (int) $shop->passes()->whereBetween('created_at', [$from, $to])->sum('price'),
            ],
            'trend' => $this->trend($shop, $from, $to, $days > 62 ? 'month' : 'day'),
            'resources' => $this->resources($shop, $from, $to),
            'services' => $this->services($shop, $from, $to),
            'customers' => $this->customers($shop, $from, $to),
            'channels' => $this->channels($shop, $from, $to),
        ];
    }

    // 매출 = 완료된 예약의 이용 금액 합 (회원권으로 처리한 예약도 서비스를 제공했으므로 포함)
    public function revenue(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $this->inPeriod($shop, $from, $to)->where('status', 'completed')->sum('total_price');
    }

    /** @return array<int, array{label:string, value:int, count:int}> 날짜(또는 달)별 매출. 매출 없는 날도 0 으로 채운다 */
    public function trend(Shop $shop, CarbonImmutable $from, CarbonImmutable $to, string $unit): array
    {
        $format = $unit === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $rows = $this->inPeriod($shop, $from, $to)->where('status', 'completed')
            ->selectRaw("DATE_FORMAT(start_at, '{$format}') as k, SUM(total_price) as v, COUNT(*) as n")
            ->groupBy('k')->get()->keyBy('k');

        $out = [];
        $step = $unit === 'month' ? $from->startOfMonth() : $from;
        while ($step <= $to) {
            $key = $unit === 'month' ? $step->format('Y-m') : $step->format('Y-m-d');
            $out[] = [
                'key' => $key,
                'label' => $unit === 'month' ? $step->format('Y.n') : $step->format('n/j'),
                'value' => (int) ($rows[$key]->v ?? 0),
                'count' => (int) ($rows[$key]->n ?? 0),
            ];
            $step = $unit === 'month' ? $step->addMonth() : $step->addDay();
        }

        return $out;
    }

    /**
     * 담당(resource)별: 완료 건수·매출, 가동률 = 예약으로 찬 시간 / 일할 수 있는 시간
     * 가동률은 오늘까지만 센다 (아직 오지 않은 날의 빈 시간은 "놀았다"가 아니므로)
     */
    public function resources(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $until = min($to, CarbonImmutable::now()->endOfDay());
        $done = $this->inPeriod($shop, $from, $to)->where('status', 'completed')
            ->selectRaw('resource_id, COUNT(*) as n, SUM(total_price) as v')
            ->groupBy('resource_id')->get()->keyBy('resource_id');

        return $shop->resources()->get()->map(function (Resource $r) use ($from, $until, $done) {
            $open = $until >= $from ? $this->openMinutes($r, $from, $until) : 0;
            $busy = $until >= $from ? $this->busyMinutes($r, $from, $until) : 0;

            return [
                'id' => $r->id,
                'name' => $r->name,
                'color' => $r->color,
                'active' => $r->is_active,
                'count' => (int) ($done[$r->id]->n ?? 0),
                'revenue' => (int) ($done[$r->id]->v ?? 0),
                'open_hours' => round($open / 60, 1),
                'busy_hours' => round($busy / 60, 1),
                'utilization' => $open ? min(100, (int) round($busy / $open * 100)) : null,
            ];
        })->values()->all();
    }

    // 상품별 매출 비중 (상위 6개 + 기타). 그룹은 1인 가격 × 인원 (예약 금액 계산과 같게 → 합이 매출과 같다)
    /** @return array<int, array{name:string, count:int, revenue:int, share:float}> */
    public function services(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = ReservationItem::query()
            ->join('reservations', 'reservations.id', '=', 'reservation_items.reservation_id')
            ->where('reservations.shop_id', $shop->id)
            ->where('reservations.status', 'completed')
            ->whereNull('reservations.deleted_at')
            ->whereBetween('reservations.start_at', [$from, $to])
            ->selectRaw('reservation_items.service_name as name, COUNT(*) as n, SUM(reservation_items.price * CASE WHEN reservations.class_session_id IS NULL THEN 1 ELSE reservations.party_size END) as v')
            ->groupBy('reservation_items.service_name')
            ->orderByDesc('v')
            ->get();

        $total = max(1, (int) $rows->sum('v'));
        $top = $rows->take(6)->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->n, 'revenue' => (int) $r->v]);
        $rest = $rows->slice(6);
        if ($rest->isNotEmpty()) {
            $top->push(['name' => '기타', 'count' => (int) $rest->sum('n'), 'revenue' => (int) $rest->sum('v')]);
        }

        return $top->map(fn ($r) => $r + ['share' => round($r['revenue'] / $total * 100, 1)])->values()->all();
    }

    /**
     * 고객: 기간에 방문(완료)한 고객 중 신규 / 재방문
     * 재방문 = 기간 전에도 방문한 적이 있거나, 기간 안에 두 번 이상 방문
     */
    public function customers(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $visits = $this->inPeriod($shop, $from, $to)->where('status', 'completed')
            ->selectRaw('customer_id, COUNT(*) as n')->groupBy('customer_id')->pluck('n', 'customer_id');

        $before = $shop->reservations()->where('status', 'completed')->where('start_at', '<', $from)
            ->whereIn('customer_id', $visits->keys())->distinct()->pluck('customer_id')->flip();

        $returning = $visits->filter(fn ($n, $id) => $n >= 2 || isset($before[$id]))->count();

        return [
            'total' => $visits->count(),
            'returning' => $returning,
            'new' => $visits->count() - $returning,
            'revisit_rate' => $this->rate($returning, $visits->count()),
        ];
    }

    /** @return array<string, int> 예약 경로별 건수 (취소 제외) */
    public function channels(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->inPeriod($shop, $from, $to)->where('status', '!=', 'canceled')
            ->selectRaw('channel, COUNT(*) as n')->groupBy('channel')->pluck('n', 'channel');

        return collect(Reservation::CHANNELS)->map(fn ($label, $key) => (int) ($rows[$key] ?? 0))->all();
    }

    // ── 안쪽 ─────────────────────────────────────

    private function date(?string $value): ?CarbonImmutable
    {
        try {
            return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function inPeriod(Shop $shop, CarbonImmutable $from, CarbonImmutable $to)
    {
        return $shop->reservations()->whereBetween('start_at', [$from, $to]);
    }

    private function completed(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->inPeriod($shop, $from, $to)->where('status', 'completed')->count();
    }

    // 보증금 순입금 = 기간에 결제된 보증금 - 기간에 환불한 금액
    private function depositNet(Shop $shop, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $paid = (int) Payment::where('shop_id', $shop->id)->whereBetween('paid_at', [$from, $to])->sum('amount');
        $refunded = (int) Payment::where('shop_id', $shop->id)->whereBetween('refunded_at', [$from, $to])->sum('refunded_amount');

        return $paid - $refunded;
    }

    // 일할 수 있는 시간(분): 날마다 근무 구간(쉬는 시간·휴무 빼고) × 한 타임 정원
    private function openMinutes(Resource $resource, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $weekly = $this->schedules->weekly($resource);
        $dated = $resource->schedules()->whereNotNull('date')
            ->whereBetween('date', [$from->toDateString(), $until->toDateString()])
            ->get()->groupBy(fn ($s) => $s->date->format('Y-m-d'));

        $minutes = 0;
        for ($day = $from->startOfDay(); $day <= $until; $day = $day->addDay()) {
            foreach ($this->slots->workingRanges($weekly[$day->dayOfWeek], $dated->get($day->format('Y-m-d'), collect()), $day) as [$a, $b]) {
                $minutes += (int) $a->diffInMinutes($b);
            }
        }

        return $minutes * max(1, $resource->capacity);
    }

    // 예약으로 찬 시간(분): 1:1·공간 예약(취소 제외) + 그룹 일정(취소 제외)
    private function busyMinutes(Resource $resource, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $sum = fn (Collection $rows) => (int) $rows->sum(fn ($r) => $r->start_at->diffInMinutes($r->end_at));

        $reservations = Reservation::where('resource_id', $resource->id)->individual()
            ->where('status', '!=', 'canceled')
            ->whereBetween('start_at', [$from, $until])
            ->get(['start_at', 'end_at']);

        $sessions = ClassSession::where('resource_id', $resource->id)->active()
            ->whereBetween('start_at', [$from, $until])
            ->get(['start_at', 'end_at']);

        return $sum($reservations) + $sum($sessions);
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole ? round($part / $whole * 100, 1) : null;
    }
}
