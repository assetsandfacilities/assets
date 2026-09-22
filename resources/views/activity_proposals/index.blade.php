@extends('layouts.admin', ['title' => 'Activity Proposals', 'subtitle' => 'Digital routing with Facilities Management as the first approval step.'])
@section('page-actions')
@if(auth()->user()->isRequestor())<a href="{{ route('activity-proposals.create') }}" class="btn-primaryx"><i class="bi bi-calendar-plus"></i> Reserve Facility</a>@endif
@endsection
@section('content')
<div class="page-tabs">
    <span class="active">
        @if(auth()->user()->isAdviserApprover()) Adviser Queue
        @elseif(auth()->user()->isDeanApprover()) Department Queue
        @elseif(auth()->user()->isFmoSuperAdmin()) All Proposals
        @elseif(auth()->user()->isFmoSide()) FMO Queue
        @else My Proposals
        @endif
    </span>
</div>
<div class="surface p-3">
    <div class="ux-search-row mb-3">
        <form method="GET" action="{{ route('activity-proposals.index') }}" class="proposal-searchbar flex-grow-1 mb-0" role="search">
            <input type="hidden" name="status" value="{{ $filterStatus }}">
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="to" value="{{ $to }}">
            <input type="hidden" name="department" value="{{ $departmentId }}">
            <input type="hidden" name="facility" value="{{ $facilityId }}">
            <div class="proposal-search-icon"><i class="bi bi-search"></i></div>
            <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search requester, activity title, proposal no., or venue" aria-label="Search activity proposals">
            <button class="btn-primaryx" type="submit">Search</button>
        </form>
        <button class="btn-soft ux-filter-button" type="button" data-bs-toggle="modal" data-bs-target="#proposalFiltersModal"><i class="bi bi-sliders"></i> Filters @if($filterStatus || $from || $to || $departmentId || $facilityId)<span class="ux-filter-dot"></span>@endif</button>
        @if(request('search') || $filterStatus || $from || $to || $departmentId || $facilityId)
            <a class="btn-soft" href="{{ route('activity-proposals.index') }}">Clear</a>
        @endif
    </div>
    @if(request('search'))
        <div class="module-note mb-3">Showing results for <strong>“{{ request('search') }}”</strong>.</div>
    @endif
    @forelse($proposals as $proposal)
    <article class="rec rec--plain">
        <div class="rec-body">
            <div class="rec-kicker">{{ $proposal->proposal_no }} &middot; {{ optional($proposal->start_at)->format('M d, Y H:i') }}</div>
            <h3 class="rec-heading"><a href="{{ route('activity-proposals.show', $proposal) }}">{{ $proposal->title }}</a></h3>
            <dl class="rec-facts">
                <div><dt>Requested by</dt><dd>{{ $proposal->user->name ?? 'Unknown' }}<span>Venue: {{ $proposal->facility->name ?? 'N/A' }}</span></dd></div>
                <div><dt>Adviser</dt><dd>{{ $proposal->adviser->name ?? 'N/A' }}<span>Dept. approver: {{ $proposal->departmentApprover->name ?? 'N/A' }}</span></dd></div>
            </dl>
            {{-- Panel feature: an emergency cancellation needs an answer from the
                 requestor, so it is surfaced on the list rather than living only
                 in the notification email. --}}
            @if($proposal->reservation && $proposal->reservation->awaitingRequestorChoice() && (int) $proposal->user_id === (int) auth()->id())
            <p class="rec-flag">
                <strong>Venue cancelled for an emergency.</strong>
                {{ $proposal->reservation->emergency_reason }}
                <a href="{{ route('reservations.rebook.edit', $proposal->reservation) }}">Choose a new schedule</a>
            </p>
            @elseif($proposal->reservation && $proposal->reservation->awaitingFmoRebooking() && (int) $proposal->user_id === (int) auth()->id())
            <p class="rec-flag"><strong>Replacement schedule sent.</strong> Waiting for the Facilities Office to confirm it.</p>
            @endif

            @if($proposal->status === 'rejected' && $proposal->rejection_reason)
            <p class="rec-flag"><strong>Reason:</strong> {{ $proposal->rejection_reason }}</p>
            @endif
        </div>
        <div class="rec-side">
            <span class="status {{ $proposal->status === 'approved' ? 'approved' : ($proposal->status === 'rejected' ? 'low' : 'pending') }}">{{ $proposal->statusLabel() }}</span>
            @if($proposal->reservation && $proposal->reservation->isPrePlotted() && !in_array($proposal->status, ['approved','rejected']))
                <span class="status pending"><i class="bi bi-pin-map"></i> {{ $proposal->reservation->isVenueApproved() ? 'Pre-Plotted — Venue Approved' : 'Pre-Plotted' }}</span>
            @endif
            <div class="rec-actions">
                <button class="btn-soft small-btn" type="button" data-bs-toggle="modal" data-bs-target="#proposalQuickView{{ $proposal->id }}"><i class="bi bi-lightning-charge"></i> Quick view</button>
                <a class="btn-approve small-btn" href="{{ route('activity-proposals.show', $proposal) }}"><i class="bi bi-eye"></i> Full details</a>
            </div>
        </div>
    </article>
    <div class="modal fade ux-modal" id="proposalQuickView{{ $proposal->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><div><div class="ux-modal-kicker">{{ $proposal->proposal_no }}</div><h5 class="modal-title">{{ $proposal->title }}</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="ux-summary-grid">
                        <div><span>Requested by</span><strong>{{ $proposal->user->name ?? 'N/A' }}</strong><small>{{ $proposal->department->name ?? 'No department' }}</small></div>
                        <div><span>Venue</span><strong>{{ $proposal->facility->name ?? 'N/A' }}</strong><small>{{ $proposal->facility->location ?? '' }}</small></div>
                        <div><span>Schedule</span><strong>{{ optional($proposal->start_at)->format('M d, Y') }}</strong><small>{{ optional($proposal->start_at)->format('h:i A') }} – {{ optional($proposal->end_at)->format('h:i A') }}</small></div>
                        <div><span>Routing status</span><strong>{{ $proposal->statusLabel() }}</strong><small>{{ $proposal->reservation?->displayStatus() ?? 'No linked reservation' }}</small></div>
                    </div>
                    @if($proposal->program_flow)<div class="ux-preview-block"><span>Program flow preview</span><p>{{ \Illuminate\Support\Str::limit($proposal->program_flow, 320) }}</p></div>@endif
                </div>
                <div class="modal-footer"><button class="btn-soft" type="button" data-bs-dismiss="modal">Close</button><a class="btn-primaryx" href="{{ route('activity-proposals.show', $proposal) }}">Open full details</a></div>
            </div>
        </div>
    </div>
    @empty
    <div class="empty-state">No activity proposals found.</div>
    @endforelse
    {{ $proposals->links('vendor.pagination.custom') }}
</div>

<div class="modal fade ux-modal" id="proposalFiltersModal" tabindex="-1" aria-labelledby="proposalFiltersTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <form method="GET" action="{{ route('activity-proposals.index') }}" class="modal-content">
            <div class="modal-header"><div><div class="ux-modal-kicker">Activity proposals</div><h5 class="modal-title" id="proposalFiltersTitle">Filter proposals</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="search" value="{{ request('search') }}">
                <div class="ux-filter-grid">
                    <div><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option><option value="pending" @selected($filterStatus==='pending')>In approval</option><option value="approved" @selected($filterStatus==='approved')>Approved</option><option value="rejected" @selected($filterStatus==='rejected')>Rejected</option></select></div>
                    <div><label class="form-label">Department</label><select name="department" class="form-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int)$departmentId === (int)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
                    <div><label class="form-label">Venue</label><select name="facility" class="form-select"><option value="">All venues</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}" @selected((int)$facilityId === (int)$facility->id)>{{ $facility->name }}</option>@endforeach</select></div>
                    <div><label class="form-label">From date</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
                    <div><label class="form-label">To date</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
                </div>
            </div>
            <div class="modal-footer"><a class="btn-soft" href="{{ route('activity-proposals.index') }}">Reset all</a><button class="btn-primaryx" type="submit"><i class="bi bi-funnel"></i> Apply filters</button></div>
        </form>
    </div>
</div>
@endsection
