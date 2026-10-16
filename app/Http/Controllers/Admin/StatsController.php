<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 통계 (대표만): 기간을 고르면 매출 추이·담당별 실적·상품 비중·재방문율
class StatsController extends Controller
{
    public function index(Request $request, StatsService $stats): View
    {
        [$from, $to, $preset] = $stats->period((string) $request->input('period', 'this_month'), $request->input('from'), $request->input('to'));

        return view('admin.stats.index', [
            'preset' => $preset,
            'presets' => StatsService::PRESETS,
            'data' => $stats->summary($request->user('admin')->shop, $from, $to),
            'channels' => Reservation::CHANNELS,
        ]);
    }
}
