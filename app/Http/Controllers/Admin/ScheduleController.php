<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleExceptionRequest;
use App\Http\Requests\Admin\WeeklyScheduleRequest;
use App\Models\Resource;
use App\Models\ResourceSchedule;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// resource 한 명(한 곳)의 근무표: 매주 반복 + 특정 날짜
class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedules) {}

    public function edit(Resource $resource): View
    {
        return view('admin.schedules.edit', [
            'resource' => $resource,
            'weekly' => $this->schedules->weekly($resource),
            'exceptions' => $this->schedules->upcomingExceptions($resource),
        ]);
    }

    public function updateWeekly(WeeklyScheduleRequest $request, Resource $resource): RedirectResponse
    {
        $this->schedules->saveWeekly($resource, $request->days());

        return back()->with('status', '매주 근무표를 저장했습니다.');
    }

    public function storeException(ScheduleExceptionRequest $request, Resource $resource): RedirectResponse
    {
        $this->schedules->addException($resource, $request->validated());

        return back()->with('status', '날짜 일정을 추가했습니다.');
    }

    public function destroyException(Resource $resource, ResourceSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->resource_id === $resource->id && $schedule->date, 404);

        $schedule->delete();

        return back()->with('status', '날짜 일정을 삭제했습니다.');
    }
}
