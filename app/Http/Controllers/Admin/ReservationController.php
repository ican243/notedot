<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveReservationRequest;
use App\Http\Requests\Admin\ReservationRequest;
use App\Http\Requests\Admin\ReservationStatusRequest;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Services\PassService;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// 캘린더에서 쓰는 예약 API (모두 JSON). 실제 일은 ReservationService 가 한다
class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations, private PaymentService $payments) {}

    public function store(ReservationRequest $request): JsonResponse
    {
        $staff = $request->user('admin');
        abort_unless($staff->canAccessResource($request->input('resource_id')), 403);

        $reservation = $this->reservations->create($staff->shop, $staff, $request->validated());

        return response()->json(['id' => $reservation->id, 'message' => '예약을 만들었습니다.'], 201);
    }

    // 수정 창에 채울 내용 + 변경 이력
    public function show(Request $request, Reservation $reservation, PassService $passes): JsonResponse
    {
        $this->authorizeResource($request, $reservation);
        $reservation->load(['customer', 'resource', 'items']);

        $logs = AuditLog::where('target_type', 'reservations')->where('target_id', $reservation->id)
            ->with('staff')->latest('id')->limit(20)->get();

        return response()->json([
            'id' => $reservation->id,
            'customer' => [
                'id' => $reservation->customer->id,
                'name' => $reservation->customer->name,
                'phone' => $reservation->customer->maskedPhone(),
                'no_show_count' => $reservation->customer->no_show_count,
                'points' => $reservation->customer->points,
                'url' => route('admin.customers.show', $reservation->customer),
            ],
            'passes' => $passes->summaryFor($reservation),
            'payment' => $this->payments->summaryFor($reservation),
            'resource_id' => $reservation->resource_id,
            'service_ids' => $reservation->items->pluck('service_id'),
            'items' => $reservation->items->map->only('service_name', 'duration_min', 'price'),
            'date' => $reservation->start_at->format('Y-m-d'),
            'start_time' => $reservation->start_at->format('H:i'),
            'end_time' => $reservation->end_at->format('H:i'),
            'party_size' => $reservation->party_size,
            'status' => $reservation->status,
            'channel' => $reservation->channel,
            'total_price' => $reservation->total_price,
            'deposit' => $reservation->deposit,
            'customer_request' => $reservation->customer_request,
            'staff_memo' => $reservation->staff_memo,
            'cancel_reason' => $reservation->cancel_reason,
            'logs' => $logs->map(fn (AuditLog $l) => [
                'action' => AuditLog::ACTIONS[$l->action] ?? $l->action,
                'by' => $l->staff?->name ?? '고객',
                'at' => $l->created_at->format('m/d H:i'),
                'changes' => $l->changes,
            ]),
        ]);
    }

    public function update(ReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $staff = $request->user('admin');
        $this->authorizeResource($request, $reservation);
        abort_unless($staff->canAccessResource($request->input('resource_id')), 403);

        $this->reservations->update($reservation, $staff, $request->validated());

        return response()->json(['message' => '예약을 수정했습니다.']);
    }

    public function move(MoveReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeResource($request, $reservation);

        $this->reservations->move($reservation, $request->user('admin'), $request->start());

        return response()->json(['message' => '시간을 옮겼습니다.']);
    }

    public function status(ReservationStatusRequest $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeResource($request, $reservation);

        $staff = $request->user('admin');
        $refund = (int) $request->input('refund_amount');
        abort_if($refund > 0 && ! $staff->isOwner(), 403);   // 돈을 돌려주는 일은 대표만

        $this->reservations->changeStatus($reservation, $staff, $request->input('status'), $request->input('reason'));
        $message = Reservation::STATUSES[$request->input('status')].'(으)로 바꿨습니다.';

        // 취소하면서 보증금 환불. 예약 취소는 이미 끝났으므로, 환불이 실패하면 알려 주고 따로 다시 시도하게 한다
        if ($request->input('status') === 'canceled' && $refund > 0 && $reservation->payment) {
            try {
                $this->payments->refund($reservation->payment, $staff, $refund, $request->input('reason') ?: '예약 취소');
                $message .= ' 보증금 '.number_format($refund).'원을 환불했습니다.';
            } catch (ValidationException $e) {
                return response()->json(['message' => '예약은 취소했지만 '.collect($e->errors())->flatten()->first(), 'warning' => true]);
            }
        }

        return response()->json(['message' => $message]);
    }

    // 보증금 환불만 따로 (대표만)
    public function refund(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeResource($request, $reservation);
        abort_unless($request->user('admin')->isOwner(), 403);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $payment = $reservation->payment;
        if (! $payment) {
            throw ValidationException::withMessages(['amount' => '결제 기록이 없습니다.']);
        }

        $this->payments->refund($payment, $request->user('admin'), $data['amount'], ($data['reason'] ?? null) ?: '매장 환불');

        return response()->json(['message' => number_format($data['amount']).'원을 환불했습니다.']);
    }

    // 직원은 자기 resource 의 예약만
    private function authorizeResource(Request $request, Reservation $reservation): void
    {
        abort_unless($request->user('admin')->canAccessResource($reservation->resource_id), 403);
    }
}
