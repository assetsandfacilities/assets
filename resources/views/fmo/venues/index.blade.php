@extends('layouts.admin', ['title' => 'Venue Management', 'subtitle' => 'Venues saved here appear as options on the Reservation Request form.'])

@section('page-actions')
<a class="btn-primaryx" href="{{ route('fmo.venues.create') }}"><i class="bi bi-building-add"></i> Add Venue</a>
@endsection

@section('content')

<div class="surface p-3 mb-3">
    <div class="chip-row">
        <a class="chip {{ $status === '' ? 'active' : '' }}" href="{{ route('fmo.venues.index', array_filter(['search' => $search])) }}"><i class="bi bi-collection"></i> All</a>
        <a class="chip {{ $status === 'active' ? 'active' : '' }}" href="{{ route('fmo.venues.index', array_filter(['status' => 'active', 'search' => $search])) }}"><i class="bi bi-check2-circle"></i> Active</a>
        <a class="chip {{ $status === 'inactive' ? 'active' : '' }}" href="{{ route('fmo.venues.index', array_filter(['status' => 'inactive', 'search' => $search])) }}"><i class="bi bi-slash-circle"></i> Inactive</a>
    </div>
    <form method="GET" class="search-strip mb-0">
        <input type="hidden" name="status" value="{{ $status }}">
        <i class="bi bi-search"></i>
        <input class="search-input" name="search" value="{{ $search }}" placeholder="Search venue name, code, or location...">
        <button class="btn-primaryx" type="submit"><i class="bi bi-funnel"></i> Search</button>
    </form>
</div>

<div class="surface p-3">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Code</th><th>Venue</th><th>Location</th><th>Capacity</th><th>Resources</th><th>Reservations</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse($venues as $venue)
                <tr>
                    <td data-label="Code"><span class="code-badge">{{ $venue->code }}</span></td>
                    <td data-label="Venue" style="font-weight:700">{{ $venue->name }}</td>
                    <td data-label="Location">{{ $venue->location ?: 'N/A' }}</td>
                    <td data-label="Capacity">{{ $venue->capacity }}</td>
                    <td data-label="Resources"><span class="tiny">{{ Str::limit($venue->resources ?: 'N/A', 45) }}</span></td>
                    <td data-label="Reservations">{{ $venue->reservations_count }}</td>
                    <td data-label="Status"><span class="status {{ $venue->is_active ? 'approved' : 'low' }}">{{ $venue->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td data-label="Actions">
                        <a class="btn-soft small-btn" href="{{ route('fmo.venues.show', $venue) }}"><i class="bi bi-eye"></i> View</a>
                        <a class="btn-soft small-btn" href="{{ route('fmo.venues.edit', $venue) }}"><i class="bi bi-pencil-square"></i> Edit</a>
                        <form method="POST" action="{{ route('fmo.venues.toggle', $venue) }}" class="d-inline">@csrf
                            <button class="btn-soft small-btn">{{ $venue->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                        @if(auth()->user()->canDeleteFacilityRecords())
                            @if($venue->reservations_count > 0)
                                <span class="tiny-2" title="Protected: this venue has reservation history."><i class="bi bi-shield-lock"></i> Protected</span>
                            @else
                                <form method="POST" action="{{ route('fmo.venues.destroy', $venue) }}" class="d-inline" data-confirm="Delete this venue?">@csrf @method('DELETE')
                                    <button class="btn-reject small-btn"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-state">No venues found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $venues->links('vendor.pagination.custom') }}</div>
</div>

<div class="note-callout mt-3">
    <i class="bi bi-shield-check"></i>
    <div>A venue that already has reservation records cannot be deleted, so historical reservations are never lost. Deactivate it instead — it disappears from the Reservation form while every past booking stays intact.</div>
</div>
@endsection
