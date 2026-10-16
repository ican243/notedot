<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 예약 캘린더 화면 + 캘린더가 불러가는 예약 목록(JSON)
class CalendarController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    public function index(Request $request): View
    {
        $staff = $request->user('admin');
        $shop = $staff->shop;
        $visible = $staff->visibleResourceIds();

        $resources = $shop->resources()->whereIn('id', $visible)->with('services:id')->get();
        $services = $shop->services()->where('booking_type', '!=', 'group')->get();
        [$slotMin, $slotMax] = $this->calendar->visibleRange($shop);

        // 화면(JavaScript)에 넘길 설정 한 묶음
        $config = [
            'urls' => [
                'events' => route('admin.calendar.events'),
                'store' => route('admin.reservations.store'),
                'reservation' => route('admin.reservations.show', '__ID__'),
                'move' => route('admin.reservations.move', '__ID__'),
                'status' => route('admin.reservations.status', '__ID__'),
                'customers' => route('admin.customers.search'),
            ],
            'resources' => $resources->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'type' => $r->type, 'maxParty' => $r->max_party, 'color' => $r->color, 'active' => $r->is_active,
                'services' => $r->services->pluck('id'),
            ]),
            'services' => $services->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'category' => $s->category,
                'duration' => $s->duration_min, 'buffer' => $s->buffer_min, 'price' => $s->price, 'active' => $s->is_active,
            ]),
            'slotUnit' => $shop->slot_unit,
            'slotMin' => $slotMin,
            'slotMax' => $slotMax,
            'businessHours' => $this->calendar->businessHours($shop),
            'statuses' => Reservation::STATUSES,
            'channels' => Reservation::CHANNELS,
            'labels' => ['resource' => term('resource'), 'service' => term('service'), 'customer' => term('customer')],
        ];

        return view('admin.calendar', ['config' => $config, 'hasResources' => $resources->isNotEmpty()]);
    }

    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'resources' => ['array'],
            'resources.*' => ['integer'],
        ]);
        $tz = config('app.timezone');
        $staff = $request->user('admin');

        return response()->json($this->calendar->events(
            $staff->shop,
            $staff,
            CarbonImmutable::parse($request->input('start'))->setTimezone($tz),
            CarbonImmutable::parse($request->input('end'))->setTimezone($tz),
            array_map('intval', $request->input('resources', [])),
            $request->boolean('canceled'),
        ));
    }
}
