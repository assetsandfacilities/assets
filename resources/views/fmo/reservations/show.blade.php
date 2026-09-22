@extends('layouts.admin', ['title' => 'Reservation Details', 'subtitle' => 'Everything the requestor submitted, plus who has approved and who has not.'])

@section('page-actions')
<a class="btn-soft" href="{{ route('fmo.reservations.index') }}"><i class="bi bi-arrow-left"></i> Back to Requests</a>

@endsection

@section('content')
@php $user = auth()->user(); @endphp

<div class="surface p-3 mb-3">
    <div class="module-head mb-2">
        <div>
            <h2 class="module-title" style="font-size:18px">{{ $reservation->reservation_no }}</h2>
            <div class="module-note">{{ $reservation->title }}</div>
        </div>
        <span class="status {{ $reservation->status === 'approved' ? 'approved' : ($reservation->status === 'rejected' ? 'low' : 'pending') }}">{{ $reservation->displayStatus() }}</span>
    </div>

    @if($reservation->isPrePlotted())
    @php $competing = $reservation->priorReservations()->with('user')->orderBy('created_at')->get(); @endphp
    <div class="note-callout mb-3">
        <i class="bi bi-pin-map"></i>
        <div>
            <strong>Pre-Plotted Venue Slot — this request was submitted after an earlier same-date request</strong><br>
            @if($reservation->isVenueApproved())
                FMO has already approved this venue slot. The Activity Proposal still follows its separate approval trail.
            @else
                The venue is not confirmed yet. Approve Venue is step 1. Approve Request remains visible as step 2, but it only becomes active after the venue is approved.
            @endif
            @if($competing->count())
                <div class="tiny mt-2"><strong>Earlier active same-date request{{ $competing->count() === 1 ? '' : 's' }}:</strong></div>
            @else
                <div class="tiny mt-2">The earlier same-date request is no longer active, but this request remains tagged as pre-plotted because that was its state when submitted.</div>
            @endif
            <ul class="mb-0 mt-2 ps-3">
                @foreach($competing as $other)
                    <li class="tiny">
                        <a href="{{ route('fmo.reservations.show', $other) }}">{{ $other->reservation_no }}</a>
                        — {{ $other->user->name ?? 'N/A' }},
                        {{ optional($other->start_at)->format('M d, Y h:i A') }} to {{ optional($other->end_at)->format('h:i A') }}
                        <span class="tag">{{ ucfirst($other->status) }}</span>
                    </li>
                @endforeach
            </ul>
            <button class="btn-soft small-btn mt-3" type="button" data-bs-toggle="modal" data-bs-target="#prePlotConflictModal"><i class="bi bi-columns-gap"></i> Compare conflicting requests</button>
        </div>
    </div>
    @endif

    {{-- FMO actions: pre-plotted requests deliberately expose TWO separate approvals. --}}
    @php
        $isAvailableFmoReviewer = $proposal
            && $proposal->isAwaitingFmo()
            && $user->isFmoSide()
            && ($proposal->facilities_mgmt_id === null || (int)$user->id === (int)$proposal->facilities_mgmt_id);
        $canApproveProposalRequest = $proposal
            && $proposal->isAwaitingFmo()
            && !$proposal->fmo_signed_at
            && ($user->isFmoSuperAdmin() || $isAvailableFmoReviewer);
        $canRejectHere = $reservation->isPending() && (
            !$proposal
            || $user->isFmoSuperAdmin()
            || $isAvailableFmoReviewer
        );
    @endphp
    <div class="request-actions mb-3" style="justify-content:flex-start">
        @if($reservation->isPending() && $reservation->isPrePlotted() && !$reservation->isVenueApproved())
            <form method="POST" action="{{ route('fmo.reservations.approve-venue', $reservation) }}" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this request?" data-confirm="Please verify the requester, venue, and schedule before continuing." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($reservation->user->name ?? 'N/A'), 'Venue: '.($reservation->facility->name ?? 'N/A'), 'Schedule: '.(optional($reservation->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf
                <button class="btn-approve"><i class="bi bi-pin-map-fill"></i> Approve Venue</button>
            </form>
        @endif

        @if($canApproveProposalRequest)
            @if($reservation->isPrePlotted() && !$reservation->isVenueApproved())
                <button class="btn-primaryx" type="button" disabled title="Approve Venue first"><i class="bi bi-building-check"></i> Approve Request — Approve Venue First</button>
            @else
                <form method="POST" action="{{ route('activity-proposals.sign-facilities', $proposal) }}" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this request?" data-confirm="Please verify the requester, venue, and schedule before continuing." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($reservation->user->name ?? 'N/A'), 'Venue: '.($reservation->facility->name ?? 'N/A'), 'Schedule: '.(optional($reservation->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf
                    <button class="btn-primaryx"><i class="bi bi-building-check"></i> Approve Request</button>
                </form>
            @endif
        @elseif(!$proposal && $reservation->isPending() && (!$reservation->isPrePlotted() || $reservation->isVenueApproved()))
            <form method="POST" action="{{ route('fmo.reservations.approve', $reservation) }}" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this request?" data-confirm="Please verify the requester, venue, and schedule before continuing." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($reservation->user->name ?? 'N/A'), 'Venue: '.($reservation->facility->name ?? 'N/A'), 'Schedule: '.(optional($reservation->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf
                <button class="btn-primaryx"><i class="bi bi-check-lg"></i> Approve Request</button>
            </form>
        @endif

        @if($proposal && !$proposal->fmo_signed_at && $proposal->isAwaitingReview())
            <form method="POST" action="{{ route('activity-proposals.sign-facilities', $proposal) }}" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this request?" data-confirm="Please verify the requester, venue, and schedule before continuing." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($reservation->user->name ?? 'N/A'), 'Venue: '.($reservation->facility->name ?? 'N/A'), 'Schedule: '.(optional($reservation->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf
                <button class="btn-primaryx"><i class="bi bi-pen"></i> Complete Legacy FMO Review</button>
            </form>
        @endif

        @if($canRejectHere)
            <button class="btn-reject" type="button" data-bs-toggle="modal" data-bs-target="#rejectRequestModal"><i class="bi bi-x-lg"></i> Reject Request</button>
        @endif

        {{-- Emergency release can be used even while the approval route is still
             in progress. Existing signatures are preserved and the request
             continues from the same approval state after the new slot is set. --}}
        @if($user->canManageFacilities() && $reservation->canBeEmergencyCancelled())
            <button class="btn-reject" type="button" data-bs-toggle="modal" data-bs-target="#emergencyCancellationModal"><i class="bi bi-exclamation-octagon"></i> Emergency Cancellation</button>
        @endif

        @if($user->canDeleteFacilityRecords())
            <form method="POST" action="{{ route('fmo.reservations.destroy', $reservation) }}" data-confirm-title="Delete reservation and proposal?" data-confirm="This removes the reservation and any linked activity proposal from the system. This cannot be undone." data-confirm-ok="Delete permanently" data-confirm-items="{{ json_encode([$reservation->reservation_no, $reservation->title, $reservation->user->name ?? 'N/A']) }}">
                @csrf @method('DELETE')
                <button class="btn-soft small-btn text-danger"><i class="bi bi-trash"></i> Delete</button>
            </form>
        @endif
    </div>


    @if($reservation->isEmergencyCancelled())
    <div class="surface p-3 mb-3" style="border-left:4px solid var(--color-accent)">
        <div class="module-head mb-2">
            <div>
                <h2 class="module-title" style="font-size:16px">Emergency cancellation</h2>
                <div class="module-note">
                    Released by {{ $reservation->emergencyCanceller->display_name ?? 'Facilities Management' }}
                    on {{ optional($reservation->emergency_cancelled_at)->format('M d, Y h:i A') }}.
                    The approval trail below is untouched.
                </div>
            </div>
            <span class="status {{ $reservation->wasRebooked() ? 'approved' : 'pending' }}">{{ $reservation->displayStatus() }}</span>
        </div>

        <table class="kv-table">
            <tr><th>Reason given</th><td>{{ $reservation->emergency_reason }}</td></tr>
            <tr><th>Original schedule</th><td>
                {{ optional($reservation->original_start_at)->format('M d, Y h:i A') }} —
                {{ optional($reservation->original_end_at)->format('h:i A') }}
                at {{ $reservation->originalFacility->name ?? $reservation->facility->name ?? 'N/A' }}
            </td></tr>
            <tr><th>Options offered</th><td>
                @switch($reservation->emergency_options)
                    @case('date') New date only (same venue) @break
                    @case('venue') Different venue only (same date) @break
                    @default New date, or a different venue on the same date
                @endswitch
            </td></tr>
            @if($reservation->rebooking_decision_note)
            <tr><th>Your last note</th><td>{{ $reservation->rebooking_decision_note }}</td></tr>
            @endif
        </table>

        @if($reservation->awaitingRequestorChoice())
            <div class="note-callout"><i class="bi bi-hourglass-split"></i>
                <div>Waiting for {{ $reservation->user->name ?? 'the requestor' }} to choose a replacement schedule.</div>
            </div>
        @endif

        @if($reservation->awaitingFmoRebooking())
            <h3 class="module-title mb-2" style="font-size:14px">Replacement the requestor chose</h3>
            <table class="kv-table">
                <tr><th>Change requested</th><td>{{ $reservation->proposed_kind === 'venue' ? 'Different venue, same date' : 'New date and time' }}</td></tr>
                <tr><th>New schedule</th><td>
                    {{ optional($reservation->proposed_start_at)->format('M d, Y h:i A') }} —
                    {{ optional($reservation->proposed_end_at)->format('h:i A') }}
                </td></tr>
                <tr><th>New venue</th><td>{{ $reservation->proposedFacility->name ?? $reservation->facility->name ?? 'N/A' }}</td></tr>
                <tr><th>Sent</th><td>{{ optional($reservation->proposed_at)->format('M d, Y h:i A') }}</td></tr>
            </table>

            @if($user->canManageFacilities())
            <div class="request-actions mb-2" style="justify-content:flex-start">
                <form method="POST" action="{{ route('fmo.reservations.rebooking.approve', $reservation) }}" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Confirm replacement schedule?" data-confirm="This will move the activity to the requestor's proposed replacement slot. Existing approvals remain in place." data-confirm-ok="Confirm new schedule">@csrf
                    <button class="btn-primaryx"><i class="bi bi-check-lg"></i> Confirm new schedule</button>
                </form>
                <button class="btn-reject" type="button" data-bs-toggle="collapse" data-bs-target="#rebook-decline"><i class="bi bi-arrow-counterclockwise"></i> Send back to requestor</button>
            </div>
            <div class="collapse" id="rebook-decline">
                <form method="POST" action="{{ route('fmo.reservations.rebooking.decline', $reservation) }}" class="p-3"
                      style="background:var(--surface-2);border:1px solid var(--line)">
                    @csrf
                    <label class="form-label">Why this schedule does not work</label>
                    <textarea name="rebooking_decision_note" class="form-control mb-2" rows="2" required minlength="5"
                              placeholder="e.g. The Covered Court is reserved for the intramurals that week — try the following Monday."></textarea>
                    <button class="btn-reject"><i class="bi bi-arrow-counterclockwise"></i> Send back</button>
                </form>
            </div>
            @endif
        @endif

        @if($reservation->wasRebooked())
            <div class="note-callout"><i class="bi bi-check-circle"></i>
                <div>
                    Moved to <strong>{{ optional($reservation->start_at)->format('M d, Y h:i A') }}</strong>
                    at <strong>{{ $reservation->facility->name ?? 'N/A' }}</strong>,
                    confirmed by {{ $reservation->rebookingDecider->display_name ?? 'Facilities Management' }}.
                </div>
            </div>
        @endif
    </div>
    @endif

    <h3 class="module-title mb-2" style="font-size:14px">Request Details</h3>
    <table class="kv-table">
        <tr><th><i class="bi bi-person me-1"></i>Requestor</th><td>{{ $reservation->user->name ?? 'N/A' }} <span class="tiny">({{ $reservation->user->email ?? '—' }})</span></td></tr>
        <tr><th><i class="bi bi-building me-1"></i>Department</th><td>{{ $reservation->user->department->name ?? 'Not assigned' }}</td></tr>
        <tr><th><i class="bi bi-geo-alt me-1"></i>Venue</th><td>{{ $reservation->facility->name ?? 'N/A' }}{{ $reservation->facility && $reservation->facility->location ? ' — '.$reservation->facility->location : '' }}</td></tr>
        <tr><th><i class="bi bi-clock me-1"></i>Schedule</th><td>{{ optional($reservation->start_at)->format('M d, Y h:i A') }} — {{ optional($reservation->end_at)->format('M d, Y h:i A') }}</td></tr>
        <tr><th><i class="bi bi-card-text me-1"></i>Purpose</th><td class="kv-wide">{!! nl2br(e($reservation->purpose ?: 'Not specified')) !!}</td></tr>
        <tr><th><i class="bi bi-calendar-plus me-1"></i>Submitted</th><td>{{ optional($reservation->created_at)->format('M d, Y h:i A') }}</td></tr>
        @if($reservation->rejection_reason)
        <tr><th><i class="bi bi-exclamation-octagon me-1"></i>Rejection Reason</th><td class="kv-wide">{{ $reservation->rejection_reason }}</td></tr>
        @endif
    </table>

    @if($proposal)
    <h3 class="module-title mb-2" style="font-size:14px">Activity Proposal — {{ $proposal->proposal_no }}</h3>
    <table class="kv-table">
        <tr><th><i class="bi bi-people me-1"></i>Organization</th><td>{{ $proposal->organization_name }}</td></tr>
        <tr><th><i class="bi bi-person-badge me-1"></i>Position</th><td>{{ $proposal->requester_position ?: 'N/A' }}</td></tr>
        <tr><th><i class="bi bi-calendar3 me-1"></i>Day(s) of Activity</th><td>{{ $proposal->activity_days ?: 'N/A' }}</td></tr>
        <tr><th><i class="bi bi-person-lines-fill me-1"></i>Expected Attendees</th><td>{{ $proposal->participants_count }}</td></tr>
        <tr><th><i class="bi bi-mic me-1"></i>Speaker</th><td>{{ $proposal->speaker_name ?: 'None' }}</td></tr>
        @if($proposal->venue_other_note)
        <tr><th><i class="bi bi-pin-map me-1"></i>Venue Note</th><td>{{ $proposal->venue_other_note }}</td></tr>
        @endif
        <tr><th><i class="bi bi-list-check me-1"></i>Program Flow</th><td class="kv-wide">@if($proposal->hasProgramFlowFile())
                <div class="pf-attachment">
                    <i class="bi bi-file-earmark-text"></i>
                    <div>
                        <button type="button" class="ux-file-link" data-bs-toggle="modal" data-bs-target="#fmoProgramFlowModal"><i class="bi bi-eye"></i> {{ $proposal->program_flow_filename }}</button>
                        <div class="tiny">Preview the attachment and extracted details without leaving the reservation page.</div>
                    </div>
                </div>
                @endif{!! nl2br(e($proposal->program_flow)) !!}</td></tr>
        <tr><th><i class="bi bi-flag me-1"></i>Routing Status</th><td>{{ $proposal->statusLabel() }}</td></tr>
    </table>
    @endif
</div>

{{-- ============================ ITEMS & SERVICES ============================ --}}
<div class="surface p-3 mb-3">
    <div class="module-head mb-2">
        <div>
            <h2 class="module-title" style="font-size:16px">Items &amp; Services Requested</h2>
            <div class="module-note">Exactly what the requestor ticked on the form, with the quantities they entered.</div>
        </div>
    </div>

    @if(count($requirements) === 0 && !$otherNote)
        <div class="empty-state">The requestor did not request any items or services.</div>
    @else
        @if(count($requirements))
        <div class="table-responsive">
            <table class="req-summary-table">
                <thead><tr><th>Item / Service</th><th>Type</th><th>Quantity</th></tr></thead>
                <tbody>
                @foreach($requirements as $line)
                    <tr>
                        <td>{{ $line['name'] ?? '—' }}</td>
                        <td>
                            @if(($line['type'] ?? null) === 'service')
                                <span class="status in-use">Service</span>
                            @elseif(($line['type'] ?? null) === 'item')
                                <span class="status available">Item</span>
                            @else
                                <span class="tag">Legacy entry</span>
                            @endif
                        </td>
                        <td class="qty">
                            @if(!empty($line['quantity']))
                                {{ $line['quantity'] }}{{ !empty($line['unit']) ? ' '.$line['unit'] : '' }}
                            @else
                                <span class="tiny text-muted">Not specified</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($otherNote)
        <div class="note-callout mt-3">
            <i class="bi bi-pencil-square"></i>
            <div><strong>Others (typed by the requestor):</strong><br>{!! nl2br(e($otherNote)) !!}</div>
        </div>
        @endif
    @endif
</div>

{{-- ============================ APPROVAL TRAIL ============================ --}}
<div class="surface p-3">
    <div class="module-head mb-2">
        <div>
            <h2 class="module-title" style="font-size:16px">Approval Trail</h2>
            <div class="module-note">Who has already approved, who is currently holding it, and who has not acted yet.</div>
        </div>
        @php $progress = $reservation->approvalProgress(); @endphp
        <span class="tag">{{ $progress['done'] }} of {{ $progress['total'] }} completed</span>
    </div>

    <ul class="approval-timeline">
        @foreach($trail as $step)
            @php
                $state = $step['state'];
                $icon = match ($state) {
                    'signed' => 'bi-check-lg',
                    'waiting' => 'bi-hourglass-split',
                    'rejected', 'blocked' => 'bi-x-lg',
                    default => 'bi-dash-lg',
                };
                $label = match ($state) {
                    'signed' => $step['at'] ? 'Approved ' . $step['at']->format('M d, Y h:i A') : 'Approved',
                    'waiting' => 'Waiting for action now',
                    'rejected' => $step['at'] ? 'Rejected ' . $step['at']->format('M d, Y h:i A') : 'Rejected',
                    'blocked' => 'Not reached — routing stopped',
                    default => 'Not yet approved',
                };
            @endphp
            <li class="{{ $state }}">
                <div class="step-dot"><i class="bi {{ $icon }}"></i></div>
                <div class="step-row">
                    <div>
                        <div class="step-role">{{ $step['role'] }}</div>
                        <div class="step-name">{{ $step['name'] }}</div>
                        @if(!empty($step['note']))
                            <div class="step-note">{{ $step['note'] }}</div>
                        @endif
                    </div>
                    <span class="step-meta {{ $state }}">{{ $label }}</span>
                </div>
            </li>
        @endforeach
    </ul>

    @php
        $stillPending = $reservation->pendingApproverNames();
    @endphp
    @if(count($stillPending))
    <div class="note-callout mt-2">
        <i class="bi bi-info-circle"></i>
        <div><strong>Still waiting on:</strong> {{ implode(' · ', $stillPending) }}</div>
    </div>
    @endif
</div>
@if($reservation->isPrePlotted())
<div class="modal fade ux-modal" id="prePlotConflictModal" tabindex="-1" aria-labelledby="prePlotConflictTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><div class="ux-modal-kicker">Pre-plotted conflict</div><h5 class="modal-title" id="prePlotConflictTitle">Compare same-venue requests</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="ux-conflict-grid">
                    <section class="ux-conflict-card is-current"><div class="ux-card-label">Current / later request</div><h6>{{ $reservation->reservation_no }}</h6><dl><div><dt>Requestor</dt><dd>{{ $reservation->user->name ?? 'N/A' }}</dd></div><div><dt>Venue</dt><dd>{{ $reservation->facility->name ?? 'N/A' }}</dd></div><div><dt>Schedule</dt><dd>{{ optional($reservation->start_at)->format('M d, Y h:i A') }} – {{ optional($reservation->end_at)->format('h:i A') }}</dd></div><div><dt>Status</dt><dd>{{ $reservation->displayStatus() }}</dd></div></dl></section>
                    <section class="ux-conflict-card"><div class="ux-card-label">Earlier request{{ $competing->count() === 1 ? '' : 's' }}</div>@forelse($competing as $other)<div class="ux-conflict-other"><h6>{{ $other->reservation_no }} · {{ $other->title }}</h6><p><strong>{{ $other->user->name ?? 'N/A' }}</strong><br>{{ optional($other->start_at)->format('M d, Y h:i A') }} – {{ optional($other->end_at)->format('h:i A') }}<br>Status: {{ $other->displayStatus() }}</p><a href="{{ route('fmo.reservations.show', $other) }}">Open earlier request</a></div>@empty<p class="mb-0">The earlier conflicting request is no longer active. This record remains tagged pre-plotted because that was its state when submitted.</p>@endforelse</section>
                </div>
                <div class="reservation-window-note mt-3"><i class="bi bi-info-circle"></i><span>Approve Venue only after checking the earlier request. The system still enforces committed-slot conflict rules on the server.</span></div>
            </div>
            <div class="modal-footer"><button class="btn-primaryx" type="button" data-bs-dismiss="modal">I understand</button></div>
        </div>
    </div>
</div>
@endif

@if($canRejectHere)
<div class="modal fade ux-modal" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('fmo.reservations.reject', $reservation) }}" class="modal-content">@csrf
            <div class="modal-header"><div><div class="ux-modal-kicker ux-danger-text">FMO decision</div><h5 class="modal-title" id="rejectRequestTitle">Reject this request?</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="ux-summary-grid ux-summary-grid-compact mb-3"><div><span>Requestor</span><strong>{{ $reservation->user->name ?? 'N/A' }}</strong></div><div><span>Venue</span><strong>{{ $reservation->facility->name ?? 'N/A' }}</strong></div><div><span>Schedule</span><strong>{{ optional($reservation->start_at)->format('M d, Y h:i A') }}</strong></div></div><label class="form-label">Reason for rejection</label><textarea name="rejection_reason" class="form-control" rows="4" required minlength="5" placeholder="Explain why this request cannot proceed.">{{ old('rejection_reason') }}</textarea></div>
            <div class="modal-footer"><button class="btn-soft" type="button" data-bs-dismiss="modal">Back</button><button class="btn-reject" type="submit"><i class="bi bi-x-lg"></i> Confirm rejection</button></div>
        </form>
    </div>
</div>
@endif

@if($user->canManageFacilities() && $reservation->canBeEmergencyCancelled())
<div class="modal fade ux-modal" id="emergencyCancellationModal" tabindex="-1" aria-labelledby="emergencyCancellationTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('fmo.reservations.emergency-cancel', $reservation) }}" class="modal-content">@csrf
            <div class="modal-header"><div><div class="ux-modal-kicker ux-danger-text">Emergency venue release</div><h5 class="modal-title" id="emergencyCancellationTitle">Cancel this booking for an emergency?</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="ux-summary-grid mb-3"><div><span>Requestor</span><strong>{{ $reservation->user->name ?? 'N/A' }}</strong><small>{{ $reservation->reservation_no }}</small></div><div><span>Current venue</span><strong>{{ $reservation->facility->name ?? 'N/A' }}</strong><small>{{ $reservation->facility->location ?? '' }}</small></div><div><span>Affected date</span><strong>{{ optional($reservation->start_at)->format('M d, Y') }}</strong><small>{{ optional($reservation->start_at)->format('h:i A') }} – {{ optional($reservation->end_at)->format('h:i A') }}</small></div><div><span>Approval state</span><strong>{{ $proposal?->statusLabel() ?? ucfirst($reservation->status) }}</strong><small>Existing signatures will stay recorded.</small></div></div>
                <div class="reservation-window-note mb-3"><i class="bi bi-shield-check"></i><span>This releases only the venue/date slot. Existing approvals are preserved and the requestor will be asked to choose a replacement.</span></div>
                <label class="form-label">Reason the requestor will see</label><textarea name="emergency_reason" class="form-control mb-3" rows="4" required minlength="10" placeholder="e.g. The Auditorium is required for an urgent campus event on the same date.">{{ old('emergency_reason') }}</textarea>
                <label class="form-label">What may the requestor change?</label>
                <div class="ux-choice-list"><label><input type="radio" name="emergency_options" value="both" checked><span><strong>Either date or venue</strong><small>Pick a new date, or keep the date and move to a different venue.</small></span></label><label><input type="radio" name="emergency_options" value="date"><span><strong>New date only</strong><small>The current venue stays assigned.</small></span></label><label><input type="radio" name="emergency_options" value="venue"><span><strong>Different venue only</strong><small>The activity date stays the same.</small></span></label></div>
            </div>
            <div class="modal-footer"><button class="btn-soft" type="button" data-bs-dismiss="modal">Back</button><button class="btn-reject" type="submit"><i class="bi bi-exclamation-octagon"></i> Confirm cancellation & notify</button></div>
        </form>
    </div>
</div>
@endif

@if($proposal && $proposal->hasProgramFlowFile())
<div class="modal fade ux-modal ux-file-modal" id="fmoProgramFlowModal" tabindex="-1" aria-labelledby="fmoProgramFlowTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><div class="ux-modal-kicker">Program flow attachment</div><h5 class="modal-title" id="fmoProgramFlowTitle">{{ $proposal->program_flow_filename }}</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">@if(str_contains(strtolower((string)$proposal->program_flow_mime), 'pdf'))<iframe class="ux-pdf-frame" src="{{ route('activity-proposals.program-flow-file', $proposal) }}" title="Program flow PDF preview"></iframe>@else<div class="ux-file-placeholder"><i class="bi bi-file-earmark-word"></i><div><strong>Document preview</strong><span>DOCX/TXT content is shown below using the text extracted by the system.</span></div></div>@endif<div class="ux-preview-block mt-3"><span>Extracted / entered program flow</span><p class="ux-prewrap">{{ $proposal->program_flow ?: 'No text was extracted from this attachment.' }}</p></div></div><div class="modal-footer"><button class="btn-soft" type="button" data-bs-dismiss="modal">Close</button><a class="btn-primaryx" href="{{ route('activity-proposals.program-flow-file', $proposal) }}" target="_blank" rel="noopener">Open original</a></div></div></div>
</div>
@endif

@endsection

@push('scripts')
@if($reservation->isPrePlotted())
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('prePlotConflictModal');
    if (!el || !window.bootstrap) return;
    var key = 'nu-preplot-seen-{{ $reservation->id }}';
    try {
        if (!sessionStorage.getItem(key)) {
            sessionStorage.setItem(key, '1');
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    } catch (e) {}
});
</script>
@endif
@endpush
