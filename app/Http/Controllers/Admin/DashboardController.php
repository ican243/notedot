<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\StatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 관리자 첫 화면: 오늘·이번 주 예약, 오늘 일정 목록. 대표에게만 이번 달 매출·결제 대기
// 직원은 자기 resource 의 예약만 센다
class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats): View
    {
        $staff = $request->user('admin');
        $shop = $staff->shop;
        $ids = $staff->visibleResourceIds();
        $today = CarbonImmutable::today();

        $base = fn () => $shop->reservations()->whereIn('resource_id', $ids)->where('status', '!=', 'canceled');

        $cards = [
            ['오늘 예약', $base()->whereBetween('start_at', [$today, $today->endOfDay()])->count().'건'],
            ['이번 주 예약', $base()->whereBetween('start_at', [$today->startOfWeek(), $today->endOfWeek()])->count().'건'],
        ];
        if ($staff->isOwner()) {
            $cards[] = ['이번 달 매출', number_format($stats->revenue($shop, $today->startOfMonth(), $today->endOfMonth())).'원'];
            $cards[] = ['결제 대기', $shop->reservations()->where('status', 'pending')->whereHas('payment', fn ($q) => $q->where('status', 'ready'))->count().'건'];
        }

        return view('admin.dashboard', [
            'staff' => $staff,
            'shop' => $shop,
            'cards' => $cards,
            'todayList' => $base()->whereBetween('start_at', [$today, $today->endOfDay()])
                ->with(['customer', 'resource', 'items', 'classSession.service'])
                ->orderBy('start_at')->limit(30)->get(),
            'statuses' => Reservation::STATUSES,
        ]);
    }
}
