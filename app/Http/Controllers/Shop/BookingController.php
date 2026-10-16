<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\BookingRequest;
use App\Models\ClassSession;
use App\Models\Shop;
use App\Services\OnlineBookingService;
use App\Services\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 고객 예약 API: 가능한 시간, 그룹 일정 목록, 예약 저장 + 완료 화면
class BookingController extends Controller
{
    public function __construct(
        private SlotService $slots,
        private OnlineBookingService $booking,
    ) {}

    // 날짜별 가능한 시각 (resource_id 가 없으면 "상관없음" = 담당 resource 전체를 합쳐서)
    public function slots(Request $request, Shop $shop): JsonResponse
    {
        $request->validate(['service_id' => ['required', 'integer'], 'resource_id' => ['nullable', 'integer']]);
        $service = $this->booking->service($shop, $request->input('service_id'));
        $resources = $this->booking->resourcesFor($shop, $service)
            ->when($request->filled('resource_id'), fn ($c) => $c->where('id', $request->integer('resource_id')))
            ->values();

        $from = CarbonImmutable::today();
        $to = $this->slots->lastDay();
        $slots = $this->slots->slots($shop, $resources, $service, $from, $to);

        $days = [];
        for ($d = $from; $d <= $to; $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $days[] = [
                'date' => $key,
                'day' => $d->day,
                'weekday' => ['일', '월', '화', '수', '목', '금', '토'][$d->dayOfWeek],
                'times' => array_keys($slots[$key] ?? []),
            ];
        }

        return response()->json(['days' => $days]);
    }

    // 그룹 상품의 신청 가능한 일정
    public function sessions(Request $request, Shop $shop): JsonResponse
    {
        $request->validate(['service_id' => ['required', 'integer']]);
        $service = $this->booking->service($shop, $request->input('service_id'));

        return response()->json($this->slots->upcomingSessions($shop, $service)->map(fn (ClassSession $s) => [
            'id' => $s->id,
            'date' => $s->start_at->format('Y-m-d'),
            'label' => $s->start_at->format('n/j').' ('.['일', '월', '화', '수', '목', '금', '토'][$s->start_at->dayOfWeek].')',
            'time' => $s->start_at->format('H:i').' ~ '.$s->end_at->format('H:i'),
            'resource' => $s->resource->name,
            'left' => max(0, $s->capacity - (int) $s->taken),
            'capacity' => $s->capacity,
        ])->values());
    }

    public function store(BookingRequest $request, Shop $shop): JsonResponse
    {
        $reservation = $this->booking->book($shop, $request->validated());

        // 완료 화면은 이 브라우저에서 방금 한 예약만 보여 준다 (주소에 번호를 넣어 남의 예약을 보는 일 방지)
        $request->session()->put("booked.{$shop->id}", $reservation->id);

        // 보증금이 있으면 결제 화면으로, 없으면 완료 화면으로
        $payment = $reservation->payment;
        $next = $payment ? route('shop.pay.show', [$shop, $payment->payment_key]) : route('shop.booking.done', $shop);

        return response()->json(['redirect' => $next], 201);
    }

    public function done(Request $request, Shop $shop): View|RedirectResponse
    {
        $reservation = $shop->reservations()
            ->with(['customer', 'resource', 'items', 'classSession', 'payment'])
            ->find($request->session()->get("booked.{$shop->id}"));

        if (! $reservation) {
            return redirect()->route('shop.home', $shop);
        }

        return view('shop.done', ['shop' => $shop, 'reservation' => $reservation]);
    }
}
