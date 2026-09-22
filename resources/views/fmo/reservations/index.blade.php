@extends('layouts.admin', ['title' => 'Reservation Requests', 'subtitle' => 'Filter by status, search by requestor or title, and open the full approval trail.'])

@section('content')
@php
    $filterQuery = array_filter([
        'search' => $search,
        'from' => $from,
        'to' => $to,
        'department' => $departmentId,
        'facility' => $facilityId,
    ], fn ($value) => $value !== null && $value !== '');
    $hasAdvancedFilters = $from !== '' || $to !== '' || $departmentId || $facilityId;
@endphp

<div class="surface p-3 mb-3">
    {{-- Status filter. Filtering happens in the SQL query, not in Blade. --}}
    <div class="chip-row">
        <a class="chip {{ $status === '' ? 'active' : '' }}" href="{{ route('fmo.reservations.index', $filterQuery) }}">
            <i class="bi bi-collection"></i> All <span class="chip-count">{{ $counts['all'] }}</span>
        </a>
        <a class="chip {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('fmo.reservations.index', array_merge($filterQuery, ['status' => 'pending'])) }}">
            <i class="bi bi-hourglass-split"></i> Pending <span class="chip-count">{{ $counts['pending'] }}</span>
        </a>
        <a class="chip {{ $status === 'pre_plotted' ? 'active' : '' }}" href="{{ route('fmo.reservations.index', array_merge($filterQuery, ['status' => 'pre_plotted'])) }}">
            <i class="bi bi-pin-map"></i> Pre-Plotted <span class="chip-count">{{ $counts['pre_plotted'] }}</span>
        </a>
        <a class="chip {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('fmo.reservations.index', array_merge($filterQuery, ['status' => 'approved'])) }}">
            <i class="bi bi-check2-circle"></i> Approved <span class="chip-count">{{ $counts['approved'] }}</span>
        </a>
        <a class="chip {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('fmo.reservations.index', array_merge($filterQuery, ['status' => 'rejected'])) }}">
            <i class="bi bi-x-circle"></i> Rejected <span class="chip-count">{{ $counts['rejected'] }}</span>
        </a>
    </div>

    {{-- Search bar: requestor name, activity title, reservation no., venue. --}}
    <div class="ux-search-row">
        <form method="GET" class="search-strip mb-0 flex-grow-1">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="to" value="{{ $to }}">
            <input type="hidden" name="department" value="{{ $departmentId }}">
            <input type="hidden" name="facility" value="{{ $facilityId }}">
            <i class="bi bi-search"></i>
            <input class="search-input" name="search" value="{{ $search }}" placeholder="Search requestor name, activity title, reservation no., or venue...">
            <button class="btn-primaryx" type="submit"><i class="bi bi-search"></i> Search</button>
        </form>
        <button class="btn-soft ux-filter-button" type="button" data-bs-toggle="modal" data-bs-target="#reservationFiltersModal">
            <i class="bi bi-sliders"></i> Filters @if($hasAdvancedFilters)<span class="ux-filter-dot"></span>@endif
        </button>
        @if($search !== '' || $hasAdvancedFilters)
            <a class="btn-soft small-btn" href="{{ route('fmo.reservations.index', array_filter(['status' => $status])) }}">Clear</a>
        @endif
    </div>
    @if($hasAdvancedFilters)
        <div class="ux-active-filters mt-3">
            @if($from)<span><i class="bi bi-calendar-event"></i> From {{ \Carbon\Carbon::parse($from)->format('M d, Y') }}</span>@endif
            @if($to)<span><i class="bi bi-calendar-event"></i> To {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}</span>@endif
            @if($departmentId)<span><i class="bi bi-building"></i> {{ optional($departments->firstWhere('id', $departmentId))->name }}</span>@endif
            @if($facilityId)<span><i class="bi bi-geo-alt"></i> {{ optional($facilities->firstWhere('id', $facilityId))->name }}</span>@endif
        </div>
    @endif
</div>

<div class="surface p-3">
    <div class="table-responsive">
        <div class="rec-list">
            @forelse($reservations as $reservation)
                @php $progress = $reservation->approvalProgress(); @endphp
                <article class="rec rec--plain">
                    <div class="rec-body">
                        <div class="rec-kicker">{{ $reservation->reservation_no }} &middot; {{ $reservation->facility->name ?? 'No venue' }}</div>
                        <h3 class="rec-heading"><a href="{{ route('fmo.reservations.show', $reservation) }}">{{ $reservation->title }}</a></h3>
                        @if($reservation->activityProposal)
                            <p class="rec-desc"><i class="bi bi-file-earmark-check"></i> Linked proposal {{ $reservation->activityProposal->proposal_no }}</p>
                        @endif
                        <dl class="rec-facts">
                            <div><dt>Requestor</dt><dd>{{ $reservation->user->name ?? 'N/A' }}<span>{{ $reservation->user->department->name ?? 'No department' }}</span></dd></div>
                            <div><dt>Schedule</dt><dd>{{ optional($reservation->start_at)->format('M d, Y h:i A') }}<span>to {{ optional($reservation->end_at)->format('M d, Y h:i A') }}</span></dd></div>
                            <div><dt>Approval trail</dt><dd>{{ $progress['done'] }} of {{ $progress['total'] }} approved
                                <div class="stock-bar" style="width:100%">
                                    <div class="stock-fill {{ $progress['done'] < $progress['total'] ? 'low' : '' }}" style="width: {{ $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0 }}%"></div>
                                </div>
                            </dd></div>
                        </dl>
                    </div>
                    <div class="rec-side">
                        <span class="status {{ $reservation->status === 'approved' ? 'approved' : ($reservation->status === 'rejected' ? 'low' : 'pending') }}">{{ $reservation->displayStatus() }}</span>
                        <div class="rec-actions">
                            <button class="btn-soft small-btn" type="button" data-bs-toggle="modal" data-bs-target="#reservationQuickView{{ $reservation->id }}"><i class="bi bi-lightning-charge"></i> Quick view</button>
                            <a class="btn-primaryx small-btn" href="{{ route('fmo.reservations.show', $reservation) }}"><i class="bi bi-eye"></i> View all details</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">No reservation requests match this filter.</div>
            @endforelse
        </div>
    </div>
    <div class="mt-3">{{ $reservations->links('vendor.pagination.custom') }}</div>
</div>


@foreach($reservations as $reservation)
    @php $quickProgress = $reservation->approvalProgress(); @endphp
    <div class="modal fade ux-modal" id="reservationQuickView{{ $reservation->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><div class="ux-modal-kicker">{{ $reservation->reservation_no }}</div><h5 class="modal-title">{{ $reservation->title }}</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ux-summary-grid">
                        <div><span>Requestor</span><strong>{{ $reservation->user->name ?? 'N/A' }}</strong><small>{{ $reservation->user->department->name ?? 'No department' }}</small></div>
                        <div><span>Venue</span><strong>{{ $reservation->facility->name ?? 'N/A' }}</strong><small>{{ $reservation->facility->location ?? '' }}</small></div>
                        <div><span>Schedule</span><strong>{{ optional($reservation->start_at)->format('M d, Y') }}</strong><small>{{ optional($reservation->start_at)->format('h:i A') }} – {{ optional($reservation->end_at)->format('h:i A') }}</small></div>
                        <div><span>Status</span><strong>{{ $reservation->displayStatus() }}</strong><small>{{ $quickProgress['done'] }} of {{ $quickProgress['total'] }} approval steps done</small></div>
                    </div>
                    @if($reservation->purpose)<div class="ux-preview-block mt-3"><span>Purpose</span><p>{{ $reservation->purpose }}</p></div>@endif
                </div>
                <div class="modal-footer"><button class="btn-soft" type="button" data-bs-dismiss="modal">Close</button><a class="btn-primaryx" href="{{ route('fmo.reservations.show', $reservation) }}">Open full details</a></div>
            </div>
        </div>
    </div>
@endforeach

<div class="modal fade ux-modal" id="reservationFiltersModal" tabindex="-1" aria-labelledby="reservationFiltersTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <form method="GET" class="modal-content">
            <div class="modal-header"><div><div class="ux-modal-kicker">Reservation list</div><h5 class="modal-title" id="reservationFiltersTitle">Filter requests</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="search" value="{{ $search }}">
                <div class="ux-filter-grid">
                    <div><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option><option value="pending" @selected($status==='pending')>Pending</option><option value="pre_plotted" @selected($status==='pre_plotted')>Pre-Plotted</option><option value="approved" @selected($status==='approved')>Approved</option><option value="rejected" @selected($status==='rejected')>Rejected</option></select></div>
                    <div><label class="form-label">Department</label><select name="department" class="form-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int)$departmentId === (int)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
                    <div><label class="form-label">Venue</label><select name="facility" class="form-select"><option value="">All venues</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}" @selected((int)$facilityId === (int)$facility->id)>{{ $facility->name }}</option>@endforeach</select></div>
                    <div><label class="form-label">From date</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
                    <div><label class="form-label">To date</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
                </div>
            </div>
            <div class="modal-footer"><a class="btn-soft" href="{{ route('fmo.reservations.index') }}">Reset all</a><button class="btn-primaryx" type="submit"><i class="bi bi-funnel"></i> Apply filters</button></div>
        </form>
    </div>
</div>
@endsection
