@extends('layouts.admin', ['title' => 'Activity Proposal Details'])
@section('content')
@php
$user = auth()->user();
$steps = [
    ['First Review — Facilities Management', $proposal->fmoSigner ?? $proposal->facilitiesMgmt, $proposal->fmo_signed_at, 'pending_fmo'],
    ['Prepared By — Adviser / Program Chair', $proposal->adviserSigner ?? $proposal->adviser, $proposal->adviser_signed_at, 'pending_adviser'],
    ['Noted By — Dean / Principal', $proposal->departmentSigner ?? $proposal->departmentApprover, $proposal->department_signed_at, 'pending_noted'],
    ['Noted By — SDAO', $proposal->sdaoSigner ?? $proposal->sdao, $proposal->sdao_signed_at, 'pending_noted'],
    ['Reviewed By — Academic Director', $proposal->academicDirectorSigner ?? $proposal->academicDirector, $proposal->academic_director_signed_at, 'pending_review'],
    ['Approved By — Executive Director', $proposal->executiveSigner ?? $proposal->executiveDirector, $proposal->executive_signed_at, 'pending_executive'],
];
@endphp
<div class="panel-grid-2 activity-proposal-detail">
    <div class="surface p-3">
        <div class="module-head mb-2">
            <div><h2 class="module-title" style="font-size:18px">{{ $proposal->proposal_no }}</h2><div class="module-note">{{ $proposal->title }}</div></div>
            <span class="status {{ $proposal->status === 'approved' ? 'approved' : ($proposal->status === 'rejected' ? 'low' : 'pending') }}">{{ $proposal->statusLabel() }}</span>
        </div>

        @if(auth()->user()->canDeleteFacilityRecords())
        <form method="POST" action="{{ route('activity-proposals.destroy', $proposal) }}" class="mb-2" data-confirm-title="Delete activity proposal?" data-confirm="This will permanently remove the activity proposal and its linked reservation record." data-confirm-ok="Delete permanently" data-confirm-items="{{ json_encode([$proposal->proposal_no, $proposal->title, $proposal->user->name ?? 'N/A']) }}">@csrf @method('DELETE')
            <button class="btn-soft small-btn text-danger"><i class="bi bi-trash"></i> Delete Proposal (Super Admin)</button>
        </form>
        @endif

        <table class="kv-table">
            <tr><th>Organization</th><td>{{ $proposal->organization_name }}</td></tr>
            <tr><th>Requested By</th><td>{{ $proposal->user->name ?? 'N/A' }} @if($proposal->requester_position)({{ $proposal->requester_position }})@endif</td></tr>
            <tr><th>Venue</th><td>{{ $proposal->facility->name ?? 'N/A' }} — {{ $proposal->facility->location ?? '' }} @if($proposal->venue_other_note)({{ $proposal->venue_other_note }})@endif</td></tr>
            <tr><th>Day(s) of Activity</th><td>{{ $proposal->activity_days ?: 'N/A' }}</td></tr>
            <tr><th>Schedule</th><td>{{ optional($proposal->start_at)->format('m/d/Y h:i A') }} – {{ optional($proposal->end_at)->format('m/d/Y h:i A') }}</td></tr>
            <tr><th>Expected Attendees</th><td>{{ $proposal->participants_count }}</td></tr>
            <tr><th>Speaker</th><td>{{ $proposal->speaker_name ?: 'N/A' }}</td></tr>
            <tr>
                <th>Venue Slot</th>
                <td>
                    <span class="status {{ $proposal->reservation && $proposal->reservation->status === 'approved' ? 'approved' : ($proposal->reservation && $proposal->reservation->status === 'rejected' ? 'low' : 'pending') }}">
                        {{ $proposal->reservation ? $proposal->reservation->displayStatus() : 'N/A' }}
                    </span>
                    @if($proposal->reservation && $proposal->reservation->isPrePlotted())
                        <span class="tiny text-muted ms-1">{{ $proposal->reservation->isVenueApproved() ? '(pre-plotted — venue approved by FMO)' : '(pre-plotted — awaiting venue confirmation)' }}</span>
                    @endif
                </td>
            </tr>
            <tr><th>Items & Services Needed</th><td class="kv-wide">
                @forelse($proposal->requirementLines() as $line)<span class="tag">{{ $line['name'] ?? '' }}@if(!empty($line['quantity'])) × {{ $line['quantity'] }}@endif</span>@empty None specified @endforelse
                @if($proposal->equipment_other_note)<div class="tiny mt-2"><strong>Others:</strong> {{ $proposal->equipment_other_note }}</div>@endif
            </td></tr>
            <tr><th>Program Flow</th><td class="kv-wide">@if($proposal->hasProgramFlowFile())
                <div class="pf-attachment">
                    <i class="bi bi-file-earmark-text"></i>
                    <div>
                        <button type="button" class="ux-file-link" data-bs-toggle="modal" data-bs-target="#programFlowModal"><i class="bi bi-eye"></i> {{ $proposal->program_flow_filename }}</button>
                        <div class="tiny">{{ $proposal->program_flow_extracted ? 'Preview the attached file and extracted text without leaving this page.' : 'Preview the attachment and the requestor-entered text without leaving this page.' }}</div>
                    </div>
                </div>
                @endif{!! nl2br(e($proposal->program_flow)) !!}</td></tr>
        </table>

        <div class="module-note mb-2" style="font-weight:700;color:var(--ink-900);font-size:12.5px">
            {{ $proposal->reservation && $proposal->reservation->isPrePlotted()
                ? 'Approval Trail — Pre-Plotted Venue Confirmation First, then FMO Request Review'
                : 'Approval Trail — Facilities Management Request Review First' }}
        </div>
        <ul class="approval-timeline">
            @if($proposal->reservation && $proposal->reservation->isPrePlotted())
                @php
                    $venueApproved = $proposal->reservation->isVenueApproved();
                    $venueAt = $proposal->reservation->venue_reviewed_at;
                    $venuePerson = $proposal->reservation->venueReviewer;
                @endphp
                <li class="{{ $venueApproved ? 'signed' : 'pending' }}">
                    <div class="step-dot"><i class="bi {{ $venueApproved ? 'bi-check-lg' : 'bi-hourglass-split' }}"></i></div>
                    <div class="step-row">
                        <div>
                            <div class="step-role">Venue Slot Approved</div>
                            <div class="step-name">{{ $venuePerson->display_name ?? 'Awaiting FMO venue decision' }}</div>
                        </div>
                        <span class="step-meta {{ $venueApproved ? 'signed' : 'pending' }}">{{ $venueApproved ? 'Approved '.$venueAt?->format('Y-m-d H:i') : 'Waiting for FMO action' }}</span>
                    </div>
                </li>
            @endif
            @foreach($steps as [$roleLabel, $person, $signedAt, $stage])
                @php
                    if ($signedAt) {
                        $stepState = 'signed';
                    } elseif ($proposal->status === 'rejected') {
                        $stepState = 'blocked';
                    } elseif ($stage === 'pending_fmo' && $proposal->status === 'pending_fmo') {
                        $stepState = $proposal->reservation && $proposal->reservation->isPrePlotted() && !$proposal->reservation->isVenueApproved()
                            ? 'pending' : 'waiting';
                    } elseif ($stage === 'pending_noted' && $proposal->status === 'pending_noted') {
                        if (str_contains($roleLabel, 'Dean / Principal')) {
                            $stepState = !$proposal->department_signed_at ? 'waiting' : 'signed';
                        } elseif (str_contains($roleLabel, 'SDAO')) {
                            $stepState = $proposal->department_signed_at && !$proposal->sdao_signed_at ? 'waiting' : 'pending';
                        } else {
                            $stepState = 'pending';
                        }
                    } else {
                        $stepState = $proposal->status === $stage ? 'waiting' : 'pending';
                    }
                    $stepIcon = $stepState === 'signed' ? 'bi-check-lg' : ($stepState === 'waiting' ? 'bi-hourglass-split' : ($stepState === 'blocked' ? 'bi-x-lg' : 'bi-dash-lg'));
                    $stepLabel = $stepState === 'signed'
                        ? 'Approved '.$signedAt->format('Y-m-d H:i')
                        : ($stepState === 'waiting' ? 'Waiting for action now' : ($stepState === 'blocked' ? 'Not reached — routing stopped' : 'Not yet approved'));
                @endphp
                <li class="{{ $stepState }}">
                    <div class="step-dot"><i class="bi {{ $stepIcon }}"></i></div>
                    <div class="step-row">
                        <div>
                            <div class="step-role">{{ $roleLabel }}</div>
                            <div class="step-name">{{ $person->display_name ?? 'Assigned' }}</div>
                            @if($signedAt && $person?->signature_data)<img src="{{ $person->signature_data }}" alt="E-signature" style="height:38px;max-width:170px;object-fit:contain;background:white;border-radius:0;padding:2px;margin-top:4px">@endif
                        </div>
                        <span class="step-meta {{ $stepState }}">{{ $stepLabel }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="note-callout"><i class="bi bi-shield-check"></i><div>The saved account e-signature is attached to the authenticated approval and is included in the final printable proposal.</div></div>
    </div>

    <div class="surface p-3">
        <h3 class="module-title" style="font-size:16px">Actions</h3>
        @if($proposal->status === 'rejected')<div class="alert alert-danger">Rejected at {{ $proposal->statusLabel() }} stage by {{ $proposal->rejecter->name ?? 'N/A' }}: {{ $proposal->rejection_reason }}</div>@endif

        @php
            $canFmoAct = $user->isFmoSuperAdmin() || ($user->isFmoSide() && ($proposal->facilities_mgmt_id === null || (int)$user->id === (int)$proposal->facilities_mgmt_id));
        @endphp
        @if($proposal->reservation && $proposal->reservation->isPrePlotted() && !$proposal->reservation->isVenueApproved() && $user->isFmoSide())
            <form method="POST" action="{{ route('fmo.reservations.approve-venue', $proposal->reservation) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-approve w-100 justify-content-center"><i class="bi bi-pin-map-fill"></i> Approve Venue</button></form>
        @endif

        @if($proposal->isAwaitingFmo() && !$proposal->fmo_signed_at && $canFmoAct)
            @if($proposal->reservation && $proposal->reservation->isPrePlotted() && !$proposal->reservation->isVenueApproved())
                <button class="btn-primaryx w-100 justify-content-center mb-3" type="button" disabled title="Approve Venue first"><i class="bi bi-building-check"></i> Approve Request — Approve Venue First</button>
            @else
                <form method="POST" action="{{ route('activity-proposals.sign-facilities', $proposal) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-primaryx w-100 justify-content-center"><i class="bi bi-building-check"></i> Approve Request</button></form>
            @endif
        @endif

        @if($proposal->isAwaitingAdviser() && ($user->isFmoSuperAdmin() || $user->id === $proposal->adviser_id))
            <form method="POST" action="{{ route('activity-proposals.approve-adviser', $proposal) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-approve w-100 justify-content-center"><i class="bi bi-pen"></i> Sign as Adviser / Program Chair</button></form>
        @endif

        @if($proposal->isAwaitingNoted())
            @if(!$proposal->department_signed_at && ($user->isFmoSuperAdmin() || $user->id === $proposal->department_approver_id))
                <form method="POST" action="{{ route('activity-proposals.sign-dean', $proposal) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-approve w-100 justify-content-center">Sign as Dean / Principal</button></form>
            @elseif($proposal->department_signed_at && !$proposal->sdao_signed_at && ($user->isFmoSuperAdmin() || $user->id === $proposal->sdao_id))
                <form method="POST" action="{{ route('activity-proposals.sign-sdao', $proposal) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-approve w-100 justify-content-center">Sign as SDAO</button></form>
            @endif
            <div class="tiny text-muted mb-2">Sequential routing: Dean / Principal first, then SDAO, then Academic Director.</div>
        @endif

        @if($proposal->isAwaitingReview())
            @if(!$proposal->fmo_signed_at && $canFmoAct)<form method="POST" action="{{ route('activity-proposals.sign-facilities', $proposal) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-approve w-100 justify-content-center">Complete Legacy FMO Review</button></form>@endif
            @if(!$proposal->academic_director_signed_at && ($user->isFmoSuperAdmin() || $user->id === $proposal->academic_director_id))<form method="POST" action="{{ route('activity-proposals.sign-academic-director', $proposal) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-approve w-100 justify-content-center">Sign as Academic Director</button></form>@endif
        @endif

        @if($proposal->isAwaitingExecutive() && ($user->isFmoSuperAdmin() || $user->id === $proposal->executive_director_id))
            <form method="POST" action="{{ route('activity-proposals.approve-executive', $proposal) }}" class="mb-3" data-confirm-kind="info" data-confirm-danger="false" data-confirm-title="Approve this step?" data-confirm="Please verify the activity details before recording your authenticated approval." data-confirm-ok="Confirm approval" data-confirm-items="{{ json_encode(['Requestor: '.($proposal->user->name ?? 'N/A'), 'Venue: '.($proposal->facility->name ?? 'N/A'), 'Schedule: '.(optional($proposal->start_at)->format('M d, Y h:i A') ?? 'N/A')]) }}">@csrf<button class="btn-approve w-100 justify-content-center"><i class="bi bi-check-lg"></i> Final Approve & Confirm Venue</button></form>
        @endif

        @if(!in_array($proposal->status, ['approved','rejected']))
            @php
                $canReject = $user->isFmoSuperAdmin()
                    || ($proposal->isAwaitingFmo() && $canFmoAct)
                    || ($proposal->isAwaitingAdviser() && $user->id === $proposal->adviser_id)
                    || ($proposal->isAwaitingNoted() && (
                        (!$proposal->department_signed_at && $user->id === $proposal->department_approver_id)
                        || ($proposal->department_signed_at && !$proposal->sdao_signed_at && $user->id === $proposal->sdao_id)
                    ))
                    || ($proposal->isAwaitingReview() && ((!$proposal->fmo_signed_at && $canFmoAct) || $user->id === $proposal->academic_director_id))
                    || ($proposal->isAwaitingExecutive() && $user->id === $proposal->executive_director_id);
            @endphp
            @if($canReject)
                <button class="btn-reject w-100 justify-content-center" type="button" data-bs-toggle="modal" data-bs-target="#rejectProposalModal"><i class="bi bi-x-circle"></i> Reject Proposal</button>
            @endif
        @endif

        @if($proposal->status === 'approved')
            <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> Fully approved. This proposal remains saved and searchable in the system together with its approval trail and e-signatures.</div>
        @endif
        <a class="btn-soft w-100 justify-content-center mt-2" href="{{ route('activity-proposals.index') }}"><i class="bi bi-arrow-left"></i> Back to List</a>
    </div>
</div>
@if($proposal->hasProgramFlowFile())
<div class="modal fade ux-modal ux-file-modal" id="programFlowModal" tabindex="-1" aria-labelledby="programFlowTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><div><div class="ux-modal-kicker">Program flow attachment</div><h5 class="modal-title" id="programFlowTitle">{{ $proposal->program_flow_filename }}</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                @if(str_contains(strtolower((string)$proposal->program_flow_mime), 'pdf'))
                    <iframe class="ux-pdf-frame" src="{{ route('activity-proposals.program-flow-file', $proposal) }}" title="Program flow PDF preview"></iframe>
                @else
                    <div class="ux-file-placeholder"><i class="bi bi-file-earmark-word"></i><div><strong>Document preview</strong><span>For DOCX/TXT attachments, the extracted text is shown below so you do not need to leave the system.</span></div></div>
                @endif
                <div class="ux-preview-block mt-3"><span>Extracted / entered program flow</span><p class="ux-prewrap">{{ $proposal->program_flow ?: 'No text was extracted from this attachment.' }}</p></div>
            </div>
            <div class="modal-footer"><button class="btn-soft" type="button" data-bs-dismiss="modal">Close</button><a class="btn-primaryx" href="{{ route('activity-proposals.program-flow-file', $proposal) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open original</a></div>
        </div>
    </div>
</div>
@endif

@if(isset($canReject) && $canReject)
<div class="modal fade ux-modal" id="rejectProposalModal" tabindex="-1" aria-labelledby="rejectProposalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('activity-proposals.reject', $proposal) }}" class="modal-content">@csrf
            <div class="modal-header"><div><div class="ux-modal-kicker ux-danger-text">Approval decision</div><h5 class="modal-title" id="rejectProposalTitle">Reject this proposal?</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="ux-summary-grid ux-summary-grid-compact mb-3"><div><span>Requestor</span><strong>{{ $proposal->user->name ?? 'N/A' }}</strong></div><div><span>Venue</span><strong>{{ $proposal->facility->name ?? 'N/A' }}</strong></div><div><span>Schedule</span><strong>{{ optional($proposal->start_at)->format('M d, Y h:i A') }}</strong></div></div>
                <label class="form-label">Reason for rejection</label>
                <textarea class="form-control" name="rejection_reason" rows="4" required minlength="5" placeholder="Explain why the proposal cannot proceed so the requestor knows what to correct.">{{ old('rejection_reason') }}</textarea>
            </div>
            <div class="modal-footer"><button class="btn-soft" type="button" data-bs-dismiss="modal">Back</button><button class="btn-reject" type="submit"><i class="bi bi-x-circle"></i> Confirm rejection</button></div>
        </form>
    </div>
</div>
@endif

@endsection
