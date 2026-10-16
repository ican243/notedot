<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\Service;
use App\Services\ClassSessionService;
use App\Services\ReservationService;
use App\Services\ScheduleService;
use App\Services\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 예약 가능한 시간 계산: 근무·쉬는 시간·휴무·특별 근무·기존 예약·그룹 일정·최소 준비 시간
class SlotServiceTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2030-01-07';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        $this->travelTo(CarbonImmutable::parse('2030-01-06 08:00'));   // 일요일 아침
    }

    // 지수 · 남성 커트(30분 + 정리 10분) · 미용실 30분 단위. 지수는 월~토 10:00~20:00
    private function times(string $date = self::MONDAY, ?Resource $resource = null, ?Service $service = null): array
    {
        $day = CarbonImmutable::parse($date);

        return array_keys(app(SlotService::class)->slots(
            $this->shop(),
            collect([$resource ?? $this->resourceNamed('지수')]),
            $service ?? $this->serviceNamed('남성 커트'),
            $day,
            $day,
        )[$date] ?? []);
    }

    private function book(string $time, string $resource = '지수', string $date = self::MONDAY): void
    {
        app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => '테스트', 'customer_phone' => '010-1234-5678',
            'resource_id' => $this->resourceNamed($resource)->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => $date, 'start_time' => $time,
        ]);
    }

    public function test_working_hours_make_slots_in_slot_units(): void
    {
        $times = $this->times();

        $this->assertSame('10:00', $times[0]);
        $this->assertSame('19:30', end($times));   // 19:30 + 30분 = 20:00 마감
        $this->assertCount(20, $times);
    }

    public function test_day_off_has_no_slots(): void
    {
        $this->assertSame([], $this->times('2030-01-06'));   // 일요일
    }

    // 쉬는 시간 13:00~14:00 → 13:00, 13:30 시작은 불가. 60분 상품은 12:30 도 불가 (13:30 에 끝나므로)
    public function test_break_time_is_excluded(): void
    {
        $jisu = $this->resourceNamed('지수');
        $days = app(ScheduleService::class)->weekly($jisu);
        $days[1] = ['on' => true, 'start' => '10:00', 'end' => '20:00', 'break_start' => '13:00', 'break_end' => '14:00'];
        app(ScheduleService::class)->saveWeekly($jisu, $days);

        $short = $this->times();
        $this->assertContains('12:30', $short);
        $this->assertNotContains('13:00', $short);
        $this->assertNotContains('13:30', $short);
        $this->assertContains('14:00', $short);

        $long = $this->times(service: $this->serviceNamed('여성 커트'));
        $this->assertNotContains('12:30', $long);
        $this->assertContains('12:00', $long);
    }

    // 10:00 예약(정리 ~10:40) → 10:00, 10:30 불가, 11:00 가능
    // 12:00 예약 → 11:30 시작은 내 정리 시간(~12:10)이 걸려서 불가, 12:30 은 12:00 예약 정리(~12:40)에 걸려서 불가
    public function test_existing_bookings_and_buffers_are_excluded(): void
    {
        $this->book('10:00');
        $this->book('12:00');

        $times = $this->times();
        $this->assertNotContains('10:00', $times);
        $this->assertNotContains('10:30', $times);
        $this->assertContains('11:00', $times);
        $this->assertNotContains('11:30', $times);
        $this->assertNotContains('12:30', $times);
        $this->assertContains('13:00', $times);
    }

    public function test_canceled_booking_frees_slot(): void
    {
        $this->book('10:00');
        $r = $this->shop()->reservations()->first();
        app(ReservationService::class)->changeStatus($r, null, 'canceled');

        $this->assertContains('10:00', $this->times());
    }

    public function test_other_resource_bookings_do_not_matter(): void
    {
        $this->book('10:00', '민호');

        $this->assertContains('10:00', $this->times());
    }

    public function test_group_session_blocks_instructor(): void
    {
        $haeun = $this->resourceNamed('하은', 'demo-pilates');
        app(ClassSessionService::class)->create($this->shop('demo-pilates'), null, [
            'resource_id' => $haeun->id,
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'date' => self::MONDAY, 'start_time' => '10:00',
        ]);

        $day = CarbonImmutable::parse(self::MONDAY);
        $times = array_keys(app(SlotService::class)->slots(
            $this->shop('demo-pilates'), collect([$haeun]), $this->serviceNamed('1:1 개인 레슨', 'demo-pilates'), $day, $day,
        )[self::MONDAY]);

        // 필라테스 60분 단위. 09:00 레슨은 정리까지 10:00 에 끝나서 10:00 일정과 맞닿기만 함 → 가능
        $this->assertContains('09:00', $times);
        $this->assertNotContains('10:00', $times);
        $this->assertContains('11:00', $times);      // 일정 정리 시간이 11:00 에 끝남
    }

    public function test_full_day_off_and_partial_off(): void
    {
        $jisu = $this->resourceNamed('지수');
        $schedule = app(ScheduleService::class);
        $schedule->addException($jisu, ['date' => self::MONDAY, 'type' => 'off', 'start_time' => '15:00', 'end_time' => '17:00']);
        $schedule->addException($jisu, ['date' => '2030-01-08', 'type' => 'off', 'start_time' => null, 'end_time' => null]);

        $times = $this->times();
        $this->assertContains('14:30', $times);
        $this->assertNotContains('15:00', $times);
        $this->assertNotContains('16:30', $times);
        $this->assertContains('17:00', $times);

        $this->assertSame([], $this->times('2030-01-08'));
    }

    // 쉬는 일요일에 특별 근무 12:00~15:00
    public function test_special_work_day_replaces_weekly_hours(): void
    {
        app(ScheduleService::class)->addException($this->resourceNamed('지수'), [
            'date' => '2030-01-13', 'type' => 'work', 'start_time' => '12:00', 'end_time' => '15:00',
        ]);

        $times = $this->times('2030-01-13');
        $this->assertSame('12:00', $times[0]);
        $this->assertSame('14:30', end($times));
    }

    // 지금부터 60분 안의 시간은 온라인으로 못 잡는다
    public function test_lead_time_hides_too_soon_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 10:10'));

        $this->assertSame('11:30', $this->times()[0]);   // 11:10 이후 첫 칸
    }
}
