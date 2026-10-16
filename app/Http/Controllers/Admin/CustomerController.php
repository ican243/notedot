<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\Customer;
use App\Models\Reservation;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 고객 관리(CRM): 목록, 상세(방문 이력·메모·회원권·포인트), 이름·메모 수정
// 직원은 자기 resource 에 예약한 고객과 그 예약만, 금액(매출)은 대표에게만 보인다
class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    public function index(Request $request): View
    {
        $sort = in_array($request->input('sort'), ['recent', 'visits', 'name'], true) ? $request->input('sort') : 'recent';

        return view('admin.customers.index', [
            'customers' => $this->customers->list($request->user('admin'), $request->input('q'), $sort),
            'q' => $request->input('q'),
            'sort' => $sort,
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $staff = $request->user('admin');
        abort_unless($this->customers->canView($staff, $customer), 403);
        $resourceIds = $staff->visibleResourceIds();

        $reservations = $customer->reservations()
            ->whereIn('resource_id', $resourceIds)
            ->with(['resource', 'items'])
            ->orderByDesc('start_at')
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        $passes = $customer->passes()
            ->with(['service', 'transactions' => fn ($q) => $q->with(['staff', 'reservation'])->latest('id')])
            ->orderByRaw('canceled_at is not null, balance = 0')   // 쓸 수 있는 권이 위로
            ->latest('id')
            ->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'isOwner' => $staff->isOwner(),
            'stats' => $this->customers->stats($customer, $resourceIds),
            'reservations' => $reservations,
            'passes' => $passes,
            'points' => $customer->pointTransactions()->with(['staff', 'reservation'])->latest('id')->limit(30)->get(),
            'countServices' => $staff->shop->services()->where('booking_type', '!=', 'space')->get(),
            'statuses' => Reservation::STATUSES,
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        abort_unless($this->customers->canView($request->user('admin'), $customer), 403);
        $customer->update($request->validated());

        return back()->with('status', '고객 정보를 저장했습니다.');
    }
}
