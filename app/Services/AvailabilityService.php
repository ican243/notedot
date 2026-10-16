<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

// resource 의 시간이 비었는지 확인하는 곳. 1:1·공간 예약과 그룹 일정이 같은 규칙을 쓴다
// 반드시 DB 트랜잭션 안에서 lockResource() 를 먼저 부르고 그다음 assertFree() 를 부른다
class AvailabilityService
{
    // resource 행을 잠근다 (SELECT ... FOR UPDATE).
    // 같은 resource 에 예약·일정을 넣으려는 다른 요청은 이 트랜잭션이 끝날 때까지 여기서 기다린다 → 동시에 같은 시간 불가
    public function lockResource(Shop $shop, int|string $resourceId, bool $mustBeActive = false): Resource
    {
        $resource = Resource::where('shop_id', $shop->id)->whereKey($resourceId)->lockForUpdate()->first();

        if (! $resource) {
            throw ValidationException::withMessages(['resource_id' => term('resource', $shop).'을(를) 찾을 수 없습니다.']);
        }
        if ($mustBeActive && ! $resource->is_active) {
            throw ValidationException::withMessages(['resource_id' => "{$resource->name}은(는) 지금 예약을 받지 않습니다."]);
        }

        return $resource;
    }

    // [start, occupied) 동안 ① 1:1·공간 예약이 정원만큼 차 있거나 ② 그룹 일정이 하나라도 있으면 거절
    public function assertFree(
        Resource $resource,
        CarbonImmutable $start,
        CarbonImmutable $occupied,
        ?int $ignoreReservationId = null,
        ?int $ignoreSessionId = null,
        string $field = 'start_time',
    ): void {
        // lockForUpdate(잠금 읽기)는 "트랜잭션 시작 때 찍어 둔 사진"이 아니라 최신 저장 상태를 읽는다.
        // 일반 SELECT 로 읽으면 MySQL(REPEATABLE READ)은 옛 사진을 보여 줘서,
        // 앞 요청이 방금 저장한 예약을 못 보고 같은 시간에 또 예약이 들어간다 (동시 요청 시험에서 실제로 발생)
        $conflicts = Reservation::where('resource_id', $resource->id)
            ->individual()
            ->occupying()
            ->overlapping($start, $occupied)
            ->when($ignoreReservationId, fn ($q) => $q->whereKeyNot($ignoreReservationId))
            ->lockForUpdate()
            ->with('customer')
            ->orderBy('start_at')
            ->get();

        if ($conflicts->count() >= $resource->capacity) {
            $c = $conflicts->first();
            $this->fail($field, sprintf(
                '%s은(는) %s~%s에 이미 예약(%s)이 있습니다.',
                $resource->name, $c->start_at->format('H:i'), $c->occupied_until->format('H:i'), $c->customer->name,
            ));
        }

        // 그룹 일정은 담당자의 시간을 통째로 쓴다
        $session = ClassSession::where('resource_id', $resource->id)
            ->active()
            ->overlapping($start, $occupied)
            ->when($ignoreSessionId, fn ($q) => $q->whereKeyNot($ignoreSessionId))
            ->lockForUpdate()
            ->with('service')
            ->orderBy('start_at')
            ->first();

        if ($session) {
            $this->fail($field, sprintf(
                '%s은(는) %s~%s에 %s 일정이 있습니다.',
                $resource->name, $session->start_at->format('H:i'), $session->occupied_until->format('H:i'), $session->service->name,
            ));
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
