@extends('layouts.admin', ['title' => 'Facilities Dashboard', 'subtitle' => 'Venue reservations, approval routing, and facility resources.'])

@section('page-actions')
<a class="btn-primaryx" href="{{ route('fmo.reservations.index') }}"><i class="bi bi-inbox"></i> Reservation Requests</a>
<a class="btn-soft" href="{{ route('fmo.venues.create') }}"><i class="bi bi-building-add"></i> Add Venue</a>
@endsection

@if(($stats['pending'] ?? 0) > 0)
@section('poster')
<div class="poster-strip">
    <div class="poster-strip-text">{{ $stats['pending'] }} {{ \Illuminate\Support\Str::plural('reservation', $stats['pending']) }} {{ $stats['pending'] == 1 ? 'is' : 'are' }} waiting on a decision.</div>
    <a class="poster-strip-btn" href="{{ route('fmo.reservations.index') }}">Open the queue</a>
</div>
@endsection
@endif

@section('content')

<div class="fig-grid">
    <div class="fig">
        <div class="fig-label">Pending reservations</div>
        <div class="fig-value">{{ $stats['pending'] }}</div>
        <div class="fig-note">Not yet approved or rejected</div>
    </div>
    <div class="fig">
        <div class="fig-label">Approved reservations</div>
        <div class="fig-value">{{ $stats['approved'] }}</div>
        <div class="fig-note">{{ $stats['upcoming'] }} upcoming and confirmed</div>
    </div>
    <div class="fig">
        <div class="fig-label">Venues active / total</div>
        <div class="fig-value">{{ $stats['venues_active'] }}<span> / {{ $stats['venues_total'] }}</span></div>
        <div class="fig-note">Active venues appear on the request form</div>
    </div>
    <div class="fig">
        <div class="fig-label">Rejected reservations</div>
        <div class="fig-value">{{ $stats['rejected'] }}</div>
        <div class="fig-note">Returned to the requestor</div>
    </div>
</div>

<div>
    <span class="section-kicker">One-click actions</span>
    <div class="quick-grid">
        <a class="quick-action" href="{{ route('fmo.reservations.index') }}">Review reservations<span>{{ $stats['pending'] }} waiting for review</span></a>
        <a class="quick-action" href="{{ route('fmo.venues.index') }}">Manage venues<span>{{ $stats['venues_active'] }} active of {{ $stats['venues_total'] }}</span></a>
        <a class="quick-action" href="{{ route('fmo.items.index') }}">Facility items<span>{{ $stats['items'] }} in the catalog</span></a>
        <a class="quick-action" href="{{ route('fmo.services.index') }}">Facility services<span>{{ $stats['services'] }} in the catalog</span></a>
        @if(auth()->user()->isFmoSuperAdmin())
        <a class="quick-action" href="{{ route('fmo.users.index') }}">Facilities users<span>{{ $stats['fmo_users'] }} {{ \Illuminate\Support\Str::plural('account', $stats['fmo_users']) }}</span></a>
        @endif
    </div>
</div>

<div class="panel-grid-2">
    <div class="surface">
        <div class="module-head">
            <div>
                <h2 class="module-title">Waiting for your review</h2>
                <div class="module-note">Reservations that have not been approved or rejected yet.</div>
            </div>
        </div>
        @forelse($pendingQueue as $reservation)
            <div class="request-card">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap"><span class="rec-ref">{{ optional($reservation->start_at)->format('M d, Y · h:i A') }}</span><span class="status pending">{{ $reservation->displayStatus() }}</span></div>
                        <div class="rec-title">{{ $reservation->title }}</div>
                        <div class="tiny">{{ $reservation->user->name ?? 'N/A' }} · {{ $reservation->facility->name ?? 'No venue' }}</div>
                    </div>
                    <a class="btn-primaryx small-btn" href="{{ route('fmo.reservations.show', $reservation) }}"><i class="bi bi-eye"></i> View all details</a>
                </div>
            </div>
        @empty
            <div class="empty-state">Nothing is waiting for review right now.</div>
        @endforelse
    </div>

    <div class="surface">
        <div class="module-head">
            <div>
                <h2 class="module-title">Upcoming confirmed schedules</h2>
                <div class="module-note">Approved bookings starting from today onwards.</div>
            </div>
        </div>
        @forelse($upcoming as $reservation)
            <div class="request-card">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap"><span class="rec-ref">{{ optional($reservation->start_at)->format('M d, Y · h:i A') }} — {{ optional($reservation->end_at)->format('h:i A') }}</span><span class="status approved">Confirmed</span></div>
                        <div class="rec-title">{{ $reservation->title }}</div>
                        <div class="tiny">{{ $reservation->facility->name ?? 'No venue' }} · {{ $reservation->user->name ?? 'N/A' }}</div>
                    </div>
                    <a class="btn-soft small-btn" href="{{ route('fmo.reservations.show', $reservation) }}"><i class="bi bi-eye"></i> Details</a>
                </div>
            </div>
        @empty
            <div class="empty-state">No upcoming confirmed schedules.</div>
        @endforelse
    </div>
</div>
@endsection
