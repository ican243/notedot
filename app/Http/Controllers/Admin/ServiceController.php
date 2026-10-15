<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// service(상품) 관리. 화면 이름은 term('service')
class ServiceController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    public function index(Request $request): View
    {
        $shop = $request->user('admin')->shop;

        return view('admin.services.index', [
            'groups' => $shop->services()->withCount('resources')->get()->groupBy(fn ($s) => $s->category ?: '분류 없음'),
        ]);
    }

    public function create(Request $request): View
    {
        $shop = $request->user('admin')->shop;

        return $this->form($request, new Service([
            'booking_type' => $shop->bookingTypes()[0],
            'duration_min' => $shop->slot_unit,
            'buffer_min' => 0,
            'capacity' => 1,
            'price' => 0,
            'deposit' => 0,
            'is_active' => true,
        ]));
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $this->catalog->saveService($request->user('admin')->shop, $request->validated());

        return redirect()->route('admin.services.index')->with('status', term('service').'을(를) 추가했습니다.');
    }

    public function edit(Request $request, Service $service): View
    {
        return $this->form($request, $service);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->catalog->saveService($request->user('admin')->shop, $request->validated(), $service);

        return redirect()->route('admin.services.index')->with('status', '저장했습니다.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('admin.services.index')->with('status', '삭제했습니다.');
    }

    private function form(Request $request, Service $service): View
    {
        $shop = $request->user('admin')->shop;

        return view('admin.services.form', [
            'service' => $service,
            'bookingTypes' => array_intersect_key(Service::BOOKING_TYPES, array_flip($shop->bookingTypes())),
            'resources' => $shop->resources()->get(),
            'checked' => $service->exists ? $service->resources()->pluck('resources.id')->all() : [],
            'categories' => $shop->services()->reorder('category')->whereNotNull('category')->distinct()->pluck('category'),
        ]);
    }
}
