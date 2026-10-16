<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassSessionRequest;
use App\Http\Requests\Admin\ClassSessionUpdateRequest;
use App\Http\Requests\Admin\MoveReservationRequest;
use App\Http\Requests\Admin\SessionBookingRequest;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Reservation;
use App\Services\ClassSessionService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// 캘린더의 그룹 일정 API (모두 JSON). 실제 일은 ClassSessionService·ReservationService 가 한다
class ClassSessionController extends Controller
{
    public function __construct(
        private ClassSessionService $sessions,
        private ReservationService $reservations,
    ) {}

    public function store(ClassSessionRequest $request): JsonResponse
    {
        $staff = $request->user('admin');
        abort_unless($staff->canAccessResource($request->input('resource_id')), 403);

        $created = $this->sessions->create($staff->shop, $staff, $request->validated());

        return response()->json(['ids' => $created->pluck('id'), 'message' => "일정 {$created->count()}개를 만들었습니다."], 201);
    }

    // 일정 창에 채울 내용: 정보 + 신청자 목록 + 변경 이력
    public function show(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorizeResource($request, $classSession);
        $classSession->load(['service', 'resource', 'reservations' => fn ($q) => $q->with('customer')->orderBy('id')]);
        $taken = $classSession->reservations->where('status', '!=', 'canceled')->sum('party_size');

        $logs = AuditLog::where('target_type', 'class_sessions')->where('target_id', $classSession->id)
            ->with('staff')->latest('id')->limit(20)->get();

        return response()->json([
            'id' => $classSession->id,
            'service' => $classSession->service->name,
            'resource' => $classSession->resource->name,
            'resource_id' => $classSession->resource_id,
            'date' => $classSession->start_at->format('Y-m-d'),
            'start_time' => $classSession->start_at->format('H:i'),
            'end_time' => $classSession->end_at->format('H:i'),
            'capacity' => $classSession->capacity,
            'taken' => $taken,
            'price' => $classSession->service->price,
            'memo' => $classSession->memo,
            'canceled' => $classSession->isCanceled(),
            'cancel_reason' => $classSession->cancel_reason,
            'attendees' => $classSession->reservations->map(fn (Reservation $r) => [
                'id' => $r->id,
                'name' => $r->customer->name,
                'phone' => $r->customer->maskedPhone(),
                'no_show_count' => $r->customer->no_show_count,
                'party_size' => $r->party_size,
                'status' => $r->status,
                'channel' => $r->channel,
            ]),
            'logs' => $logs->map(fn (AuditLog $l) => [
                'action' => AuditLog::ACTIONS[$l->action] ?? $l->action,
                'by' => $l->staff?->name ?? '고객',
                'at' => $l->created_at->format('m/d H:i'),
                'changes' => $l->changes,
            ]),
        ]);
    }

    public function update(ClassSessionUpdateRequest $request, ClassSession $classSession): JsonResponse
    {
        $this->authorizeResource($request, $classSession);

        $this->sessions->update($classSession, $request->user('admin'), $request->validated());

        return response()->json(['message' => '일정을 저장했습니다.']);
    }

    public function move(MoveReservationRequest $request, ClassSession $classSession): JsonResponse
    {
        $this->authorizeResource($request, $classSession);

        $this->sessions->move($classSession, $request->user('admin'), $request->start());

        return response()->json(['message' => '일정 시간을 옮겼습니다.']);
    }

    public function cancel(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorizeResource($request, $classSession);
        $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $this->sessions->cancel($classSession, $request->user('admin'), $request->input('reason'));

        return response()->json(['message' => '일정을 취소했습니다.']);
    }

    // 신청자 추가
    public function book(SessionBookingRequest $request, ClassSession $classSession): JsonResponse
    {
        $this->authorizeResource($request, $classSession);

        $reservation = $this->reservations->bookSession($classSession, $request->user('admin'), $request->validated());

        return response()->json(['id' => $reservation->id, 'message' => '신청을 추가했습니다.'], 201);
    }

    // 직원은 자기 resource 의 일정만
    private function authorizeResource(Request $request, ClassSession $session): void
    {
        abort_unless($request->user('admin')->canAccessResource($session->resource_id), 403);
    }
}
