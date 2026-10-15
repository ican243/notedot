<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 직원 로그인 계정 관리 (대표 전용)
class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $request->user('admin')->shop;

        return view('admin.staffs.index', [
            'staffs' => $shop->staffs()->with('resource')->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Staff(['role' => 'staff']));
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $request->user('admin')->shop->staffs()->create($request->validated());

        return redirect()->route('admin.staffs.index')->with('status', '직원을 추가했습니다.');
    }

    public function edit(Request $request, Staff $staff): View
    {
        return $this->form($request, $staff);
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);   // 비워 두면 비밀번호 유지
        }
        if ($staff->is($request->user('admin'))) {
            unset($data['role']);       // 내 권한은 내가 못 바꿈 (대표가 0명이 되는 것 방지)
        }

        $staff->update($data);

        return redirect()->route('admin.staffs.index')->with('status', '직원 정보를 저장했습니다.');
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        abort_if($staff->is($request->user('admin')), 422, '본인 계정은 삭제할 수 없습니다.');

        $staff->delete();

        return redirect()->route('admin.staffs.index')->with('status', '직원을 삭제했습니다.');
    }

    private function form(Request $request, Staff $staff): View
    {
        $shop = $request->user('admin')->shop;

        return view('admin.staffs.form', [
            'staff' => $staff,
            'isMe' => $staff->is($request->user('admin')),
            'resources' => $shop->resources()->where('type', 'person')->get(),
        ]);
    }
}
