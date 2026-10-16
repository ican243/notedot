@extends('layouts.admin')

@section('title', '알림 기록')

@section('content')
<x-admin.page-header title="알림 기록" sub="{{ term('customer') }}에게 보낸 예약 확정·전날 리마인드·취소 알림입니다.">
    <a href="{{ route('admin.settings.shop') }}#notify" class="btn-line">알림 설정</a>
</x-admin.page-header>

@if ($logs->isNotEmpty() && $logs->first()->channel === 'log')
    <p class="panel mb-4 p-4 text-sm text-sub">지금은 <b>기록만</b> 남기고 실제로 보내지 않습니다. 솔라피 키를 연결하면 문자·알림톡으로 발송됩니다.</p>
@endif

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <select name="type" class="input w-auto" aria-label="종류" onchange="this.form.submit()">
        <option value="">모든 종류</option>
        @foreach (\App\Models\NotificationLog::TYPES as $v => $l)
            <option value="{{ $v }}" @selected($type === $v)>{{ $l }}</option>
        @endforeach
    </select>
    <select name="status" class="input w-auto" aria-label="상태" onchange="this.form.submit()">
        <option value="">모든 상태</option>
        @foreach (\App\Models\NotificationLog::STATUSES as $v => $l)
            <option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>
        @endforeach
    </select>
</form>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead>
            <tr><th>시각</th><th>종류</th><th>받는 사람</th><th>경로</th><th>상태</th><th>내용</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr class="align-top">
                    <td class="whitespace-nowrap text-sub">{{ ($log->sent_at ?? $log->created_at)->format('m/d H:i') }}</td>
                    <td class="whitespace-nowrap">{{ \App\Models\NotificationLog::TYPES[$log->type] ?? $log->type }}</td>
                    <td class="whitespace-nowrap">
                        @if ($log->customer)
                            <a href="{{ route('admin.customers.show', $log->customer) }}" class="font-medium hover:underline">{{ $log->customer->name }}</a>
                        @endif
                        <span class="block text-xs text-sub">{{ $log->maskedPhone() }}</span>
                    </td>
                    <td class="whitespace-nowrap">{{ \App\Models\NotificationLog::CHANNELS[$log->channel] }}</td>
                    <td class="whitespace-nowrap">
                        <span @class(['badge', 'text-st-noshow' => $log->status === 'failed'])>{{ \App\Models\NotificationLog::STATUSES[$log->status] }}</span>
                        @if ($log->error)<span class="block max-w-48 text-xs text-st-noshow">{{ $log->error }}</span>@endif
                    </td>
                    <td class="min-w-64">
                        <details>
                            <summary class="cursor-pointer text-sub">{{ \Illuminate\Support\Str::limit(strtok($log->message, "\n"), 40) }}</summary>
                            <p class="mt-1 whitespace-pre-line">{{ $log->message }}</p>
                        </details>
                    </td>
                    <td class="text-right">
                        @if ($log->status === 'failed')
                            <form method="POST" action="{{ route('admin.notifications.retry', $log) }}">
                                @csrf
                                <button class="btn-line px-2 py-1 text-xs">다시 보내기</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-sub">아직 보낸 알림이 없습니다.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
