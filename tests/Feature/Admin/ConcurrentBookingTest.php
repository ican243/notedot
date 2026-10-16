<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Models\Shop;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Throwable;

// 여러 요청이 "정확히 같은 순간" 같은 시간을 잡으면 하나만 성공해야 한다.
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

        $dir = sys_get_temp_dir().'/notedot-race-'.getmypid();
        @mkdir($dir);
        DB::disconnect();   // 자식이 부모의 DB 연결을 같이 쓰지 않게 끊어 둔다

        $pids = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $this->worker($dir, $i, $shop->id, $data + ['customer_phone' => '010-1000-000'.$i]);
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

        $this->assertSame(1, count(array_keys($results, 'ok')), implode(', ', $results));
        $this->assertSame(self::WORKERS - 1, count(array_keys($results, 'full')), implode(', ', $results));
        $this->assertSame(1, Reservation::count());
    }

    // 자식 프로세스: 새로 접속 → 출발 신호 기다림 → 예약 시도 → 결과를 파일에 적고 바로 끝
    private function worker(string $dir, int $i, int $shopId, array $data): never
    {
        try {
            DB::reconnect();
            $shop = Shop::findOrFail($shopId);
            while (! file_exists("$dir/go")) {
                usleep(1000);
            }
            app(ReservationService::class)->create($shop, null, $data);
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
