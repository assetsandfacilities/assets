@extends('layouts.admin', ['title' => 'Notifications', 'subtitle' => 'Email and in-system updates for requisition routing and approvals.'])
@if(auth()->user()->unreadNotifications->count() || $notifications->total())
@section('page-actions')
@if(auth()->user()->unreadNotifications->count())
<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-primaryx"><i class="bi bi-check2-all"></i> Mark All as Read</button></form>
@endif
@if($notifications->total())
<form method="POST" action="{{ route('notifications.clear-all') }}" data-confirm="Clear all of your notifications? This cannot be undone.">@csrf @method('DELETE')<button class="btn-soft"><i class="bi bi-trash"></i> Clear All</button></form>
@endif
@endsection
@endif
@section('content')

<div class="surface p-3">
    @forelse($notifications as $notification)
    @php
        $data = $notification->data;
    @endphp
    <div class="request-card {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <strong class="rec-title" style="margin-top:0!important;font-size:20px!important">{{ $data['subject'] ?? 'System Notification' }}</strong>
                    @if(!$notification->read_at)
                    <span class="status pending">Unread</span>
                    @endif
                </div>
                <div class="tiny mt-1">{{ $data['message'] ?? 'No message available.' }}</div>
                <div class="tiny-2 mt-2">{{ $data['requisition_no'] ?? 'N/A' }} · {{ $data['status_label'] ?? 'N/A' }} · {{ $notification->created_at?->format('Y-m-d h:i A') }}</div>
            </div>
            <div class="request-actions">
                @if(!$notification->read_at)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="btn-soft"><i class="bi bi-check-lg"></i> Mark Read</button></form>
                @endif
                <a class="btn-approve" href="{{ $data['url'] ?? route('requisitions.index') }}"><i class="bi bi-eye"></i> Open</a>
                <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" data-confirm="Remove this notification?">@csrf @method('DELETE')<button class="btn-reject"><i class="bi bi-trash"></i></button></form>
            </div>
        </div>
    </div>
    @empty
    <div class="empty-state">No notifications yet.</div>
    @endforelse

    {{ $notifications->links('vendor.pagination.custom') }}
</div>
@endsection
