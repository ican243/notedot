<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 알림 발송 기록 (대표만): 목록·거르기, 실패한 알림 다시 보내기
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $type = array_key_exists($request->input('type'), NotificationLog::TYPES) ? $request->input('type') : null;
        $status = array_key_exists($request->input('status'), NotificationLog::STATUSES) ? $request->input('status') : null;

        $logs = $request->user('admin')->shop->notificationLogs()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['customer', 'reservation'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.notifications.index', compact('logs', 'type', 'status'));
    }

    public function retry(NotificationLog $notificationLog, NotificationService $notifications): RedirectResponse
    {
        if ($notificationLog->status !== 'failed') {
            return back()->with('error', '실패한 알림만 다시 보낼 수 있습니다.');
        }
        $notifications->retry($notificationLog);

        return back()->with('status', '다시 보냈습니다.');
    }
}
