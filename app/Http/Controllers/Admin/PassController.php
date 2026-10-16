<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BalanceAdjustRequest;
use App\Http\Requests\Admin\PassRequest;
use App\Models\Customer;
use App\Models\Pass;
use App\Services\PassService;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// 회원권 발급·조정·취소, 포인트 조정 (대표만. 고객 상세 화면의 폼에서 보낸다)
class PassController extends Controller
{
    public function __construct(private PassService $passes) {}

    public function store(PassRequest $request, Customer $customer): RedirectResponse
    {
        $pass = $this->passes->issue($customer, $request->user('admin'), $request->validated());

        return back()->with('status', "{$pass->name}을(를) 발급했습니다.");
    }

    public function adjust(BalanceAdjustRequest $request, Pass $pass): RedirectResponse
    {
        $this->passes->adjust($pass, $request->user('admin'), (int) $request->input('amount'), $request->input('memo'));

        return back()->with('status', '잔액을 조정했습니다.');
    }

    public function cancel(Request $request, Pass $pass): RedirectResponse
    {
        $request->validate(['memo' => ['nullable', 'string', 'max:200']]);
        $this->passes->cancel($pass, $request->user('admin'), $request->input('memo'));

        return back()->with('status', '권을 취소했습니다. 남은 잔액은 0 이 됩니다.');
    }

    public function adjustPoints(BalanceAdjustRequest $request, Customer $customer, PointService $points): RedirectResponse
    {
        $points->adjust($customer, $request->user('admin'), (int) $request->input('amount'), $request->input('memo'));

        return back()->with('status', '포인트를 조정했습니다.');
    }
}
