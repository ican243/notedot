<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceRequest;
use App\Models\Resource;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// resource(담당자/공간) 관리. 화면 이름은 term('resource')
class ResourceController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    public function index(Request $request): View
    {
        $shop = $request->user('admin')->shop;

        return view('admin.resources.index', [
            'resources' => $shop->resources()->withCount('services')->with('staff')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $shop = $request->user('admin')->shop;

        return $this->form($request, new Resource([
            'type' => $shop->preset()['resource_type'] ?? 'person',
            'color' => '#2463b8',
            'capacity' => 1,
            'is_active' => true,
        ]));
    }

    public function store(ResourceRequest $request): RedirectResponse
    {
        $this->catalog->saveResource($request->user('admin')->shop, $request->validated());

        return redirect()->route('admin.resources.index')->with('status', term('resource').'을(를) 추가했습니다.');
    }

    public function edit(Request $request, Resource $resource): View
    {
        return $this->form($request, $resource);
    }

    public function update(ResourceRequest $request, Resource $resource): RedirectResponse
    {
        $this->catalog->saveResource($request->user('admin')->shop, $request->validated(), $resource);

        return redirect()->route('admin.resources.index')->with('status', '저장했습니다.');
    }

    public function destroy(Resource $resource): RedirectResponse
    {
        $this->catalog->deleteResource($resource);

        return redirect()->route('admin.resources.index')->with('status', '삭제했습니다.');
    }

    private function form(Request $request, Resource $resource): View
    {
        return view('admin.resources.form', [
            'resource' => $resource,
            'services' => $request->user('admin')->shop->services()->get(),
            'checked' => $resource->exists ? $resource->services()->pluck('services.id')->all() : [],
        ]);
    }
}
