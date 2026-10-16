<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\PassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// 예약 창의 "회원권으로 처리" / "사용 취소" (JSON). 그 예약을 볼 수 있는 직원이면 누구나
class ReservationPassController extends Controller
{
    public function __construct(private PassService $passes) {}

    public function store(Request $request, Reservation $reservation): JsonResponse
    {
        $staff = $request->user('admin');
        abort_unless($staff->canAccessResource($reservation->resource_id), 403);
        $request->validate(['pass_id' => ['required', 'integer']]);

        $pass = $staff->shop->passes()->find($request->integer('pass_id'));
        if (! $pass) {
            throw ValidationException::withMessages(['pass_id' => '권을 다시 선택하세요.']);
        }

        $tx = $this->passes->useFor($reservation, $pass, $staff);

        return response()->json(['message' => "{$pass->name}에서 ".$pass->format(-$tx->amount).' 차감했습니다.']);
    }

    public function destroy(Request $request, Reservation $reservation): JsonResponse
    {
        abort_unless($request->user('admin')->canAccessResource($reservation->resource_id), 403);

        $restored = $this->passes->restoreFor($reservation, $request->user('admin'));

        return response()->json(['message' => $restored ? '회원권 사용을 취소했습니다.' : '되돌릴 사용 기록이 없습니다.']);
    }
}
