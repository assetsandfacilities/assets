@extends('layouts.admin', ['title' => 'Venue Details'])

@section('page-actions')
<a class="btn-primaryx" href="{{ route('fmo.venues.edit', $venue) }}"><i class="bi bi-pencil-square"></i> Edit Venue</a>
<a class="btn-soft" href="{{ route('fmo.venues.index') }}"><i class="bi bi-arrow-left"></i> Back</a>
@endsection

@section('content')
<div class="surface p-3 mb-3">
    <div class="module-head mb-2">
        <div>
            <h2 class="module-title" style="font-size:18px">{{ $venue->name }}</h2>
            <div class="module-note">{{ $venue->code }}</div>
        </div>
        <span class="status {{ $venue->is_active ? 'approved' : 'low' }}">{{ $venue->is_active ? 'Active' : 'Inactive' }}</span>
    </div>
    <table class="kv-table">
        <tr><th><i class="bi bi-geo-alt me-1"></i>Location</th><td>{{ $venue->location ?: 'N/A' }}</td></tr>
        <tr><th><i class="bi bi-people me-1"></i>Capacity</th><td>{{ $venue->capacity }}</td></tr>
        <tr><th><i class="bi bi-box-seam me-1"></i>Available Resources</th><td class="kv-wide">{{ $venue->resources ?: 'N/A' }}</td></tr>
        <tr><th><i class="bi bi-pin-map me-1"></i>GPS Reference</th><td>{{ $venue->latitude && $venue->longitude ? $venue->latitude.', '.$venue->longitude : 'Not set' }}</td></tr>
        <tr><th><i class="bi bi-calendar-check me-1"></i>Total Reservations</th><td>{{ $venue->reservations_count }}</td></tr>
    </table>
</div>

<div class="surface p-3">
    <div class="module-head mb-2"><div><h2 class="module-title" style="font-size:16px">Recent Reservations</h2><div class="module-note">The 10 most recent bookings for this venue.</div></div></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>No.</th><th>Requestor</th><th>Title</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($recent as $reservation)
                <tr>
                    <td data-label="No."><span class="code-badge">{{ $reservation->reservation_no }}</span></td>
                    <td data-label="Requestor">{{ $reservation->user->name ?? 'N/A' }}</td>
                    <td data-label="Title">{{ $reservation->title }}</td>
                    <td data-label="Schedule">{{ optional($reservation->start_at)->format('M d, Y h:i A') }}</td>
                    <td data-label="Status"><span class="status {{ $reservation->status === 'approved' ? 'approved' : ($reservation->status === 'rejected' ? 'low' : 'pending') }}">{{ $reservation->displayStatus() }}</span></td>
                    <td data-label="Actions"><a class="btn-soft small-btn" href="{{ route('fmo.reservations.show', $reservation) }}"><i class="bi bi-eye"></i> Details</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No reservations for this venue yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
