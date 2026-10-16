<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Pass;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Shop;
use App\Services\ClassSessionService;
use App\Services\PassService;
use App\Services\Payment\DemoGateway;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Throwable;

// 여러 요청이 "정확히 같은 순간" 같은 시간을 잡으면 하나만 성공해야 한다. 그룹 일정은 정원만큼만 성공해야 한다.
// 프로세스를 여러 개로 나눠(fork) 각자 DB 에 따로 접속해서 동시에 예약한다.
// (다른 테스트처럼 트랜잭션으로 감싸면 다른 프로세스가 데이터를 못 보므로, 이 테스트는 표를 비우는 방식을 쓴다)
class ConcurrentBookingTest extends TestCase
{
    use DatabaseTruncation;

    private const WORKERS = 8;

    public function test_only_one_parallel_booking_succeeds(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl 확장이 필요합니다.');
        }

        $this->seedDemoShops();
        $shop = $this->shop();
        $data = [
            'customer_name' => '동시',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07',
            'start_time' => '10:00',
        ];

        $results = $this->race(function (int $i) use ($shop, $data) {
            app(ReservationService::class)->create(Shop::findOrFail($shop->id), null, $data + ['customer_phone' => '010-1000-000'.$i]);
        });

        $this->assertSame(1, count(array_keys($results, 'ok')), implode(', ', $results));
        $this->assertSame(self::WORKERS - 1, count(array_keys($results, 'full')), implode(', ', $results));
        $this->assertSame(1, Reservation::count());
    }

    // 정원 6명 일정에 8명이 동시에 신청 → 정확히 6명만 성공
    public function test_parallel_session_bookings_stop_at_capacity(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl 확장이 필요합니다.');
        }

        $this->seedDemoShops();
        $shop = $this->shop('demo-pilates');
        $session = app(ClassSessionService::class)->create($shop, null, [
            'resource_id' => $this->resourceNamed('하은', 'demo-pilates')->id,
            'service_id' => $this->serviceNamed('그룹 리포머', 'demo-pilates')->id,
            'date' => '2030-01-07',
            'start_time' => '10:00',
        ])->first();

        $results = $this->race(function (int $i) use ($session) {
            app(ReservationService::class)->bookSession(ClassSession::findOrFail($session->id), null, [
                'customer_name' => '동시'.$i,
                'customer_phone' => '010-2000-000'.$i,
            ]);
        });

        $this->assertSame(6, count(array_keys($results, 'ok')), implode(', ', $results));
        $this->assertSame(2, count(array_keys($results, 'full')), implode(', ', $results));
        $this->assertSame(6, (int) Reservation::where('class_session_id', $session->id)->sum('party_size'));
    }

    // 3회 남은 권으로 서로 다른 예약 8건을 동시에 처리 → 정확히 3건만 성공, 잔액은 0 (마이너스 없음)
    public function test_parallel_pass_uses_never_overdraw(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl 확장이 필요합니다.');
        }

        $this->seedDemoShops();
        $shop = $this->shop();
        $ids = collect(range(0, self::WORKERS - 1))->map(fn (int $i) => app(ReservationService::class)->create($shop, null, [
            'customer_name' => '동시', 'customer_phone' => '010-3000-0000',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07', 'start_time' => sprintf('%02d:00', 10 + $i),
        ])->id);
        $pass = app(PassService::class)->issue(Reservation::find($ids[0])->customer, null, ['type' => 'count', 'name' => '3회권', 'price' => 0, 'total' => 3]);

        $results = $this->race(function (int $i) use ($ids, $pass) {
            app(PassService::class)->useFor(Reservation::findOrFail($ids[$i]), Pass::findOrFail($pass->id), null);
        });

        $this->assertSame(3, count(array_keys($results, 'ok')), implode(', ', $results));
        $this->assertSame(0, $pass->refresh()->balance);
        $this->assertSame(0, (int) $pass->transactions()->sum('amount'));
    }

    // 같은 예약을 8번 동시에 "회원권으로 처리" → 1번만 차감
    public function test_parallel_same_reservation_uses_pass_once(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl 확장이 필요합니다.');
        }

        $this->seedDemoShops();
        $r = app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => '동시', 'customer_phone' => '010-3000-0001',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07', 'start_time' => '10:00',
        ]);
        $pass = app(PassService::class)->issue($r->customer, null, ['type' => 'count', 'name' => '10회권', 'price' => 0, 'total' => 10]);

        $results = $this->race(function () use ($r, $pass) {
            app(PassService::class)->useFor(Reservation::findOrFail($r->id), Pass::findOrFail($pass->id), null);
        });

        $this->assertSame(1, count(array_keys($results, 'ok')), implode(', ', $results));
        $this->assertSame(9, $pass->refresh()->balance);
    }

    // 결제 완료 확인이 8번 동시에 들어와도(화면 + 웹훅 + 새로고침) 결제·확정 처리는 한 번만
    public function test_parallel_payment_confirms_once(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl 확장이 필요합니다.');
        }

        $this->seedDemoShops();
        $r = app(ReservationService::class)->create($this->shop(), null, [
            'customer_name' => '동시', 'customer_phone' => '010-3000-0002',
            'resource_id' => $this->resourceNamed('지수')->id,
            'service_ids' => [$this->serviceNamed('남성 커트')->id],
            'date' => '2030-01-07', 'start_time' => '10:00', 'status' => 'pending',
        ]);
        $r->update(['deposit' => 10000]);
        $payment = app(PaymentService::class)->start($r);
        app(DemoGateway::class)->simulate($payment, 'paid');

        $results = $this->race(function () use ($payment) {
            app(PaymentService::class)->confirm(Payment::findOrFail($payment->id));
        });

        $this->assertSame(self::WORKERS, count(array_keys($results, 'ok')), implode(', ', $results));
        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame('confirmed', $r->refresh()->status);
        $this->assertSame(1, AuditLog::where('target_id', $r->id)->where('action', 'paid')->count());
        $this->assertSame(1, AuditLog::where('target_id', $r->id)->where('action', 'status_changed')->count());
    }

    /**
     * 프로세스 WORKERS 개를 만들어 출발 신호와 함께 $attempt($i) 를 동시에 실행
     *
     * @return string[] 각 프로세스 결과: ok | full | error: ...
     */
    private function race(callable $attempt): array
    {
        $dir = sys_get_temp_dir().'/notedot-race-'.getmypid();
        @mkdir($dir);
        DB::disconnect();   // 자식이 부모의 DB 연결을 같이 쓰지 않게 끊어 둔다

        $pids = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $this->worker($dir, $i, $attempt);
            }
            $pids[] = $pid;
        }

        touch("$dir/go");   // 출발 신호: 모두 준비된 뒤 한꺼번에 출발
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = array_map(fn ($i) => @file_get_contents("$dir/$i") ?: 'missing', range(0, self::WORKERS - 1));
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
        DB::reconnect();

        return $results;
    }

    // 자식 프로세스: 새로 접속 → 출발 신호 기다림 → 시도 → 결과를 파일에 적고 바로 끝
    private function worker(string $dir, int $i, callable $attempt): never
    {
        try {
            DB::reconnect();
            while (! file_exists("$dir/go")) {
                usleep(1000);
            }
            $attempt($i);
            $result = 'ok';
        } catch (ValidationException) {
            $result = 'full';
        } catch (Throwable $e) {
            $result = 'error: '.$e->getMessage();
        }

        file_put_contents("$dir/$i", $result);
        // PHPUnit 의 종료 처리가 자식에서 돌지 않도록 바로 종료
        posix_kill(getmypid(), SIGKILL);
        exit;
    }
}
