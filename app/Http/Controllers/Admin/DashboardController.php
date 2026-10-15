<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 관리자 첫 화면 (통계는 Phase 9에서 채움)
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user('admin');

        return view('admin.dashboard', [
            'staff' => $staff,
            'shop' => $staff->shop,
        ]);
    }
}
