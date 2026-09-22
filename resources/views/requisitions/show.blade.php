@extends('layouts.admin', ['title' => 'Requisition Details'])
@section('content')
<div class="module-head">
    <div>
        <h2 class="module-title">Requisition Details</h2>
        <div class="module-note">Charge slip requisition, line items, and approval trail.</div>
    </div>
    <a href="{{ route('requisitions.index') }}" class="btn-soft small-btn"><i class="bi bi-arrow-left"></i> Back to list</a>
</div>

<div class="panel-grid-2">
    <div class="surface p-3">
        <div class="req-summary">
            <div class="req-summary-ico"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="req-summary-no">{{ $requisition->requisition_no }}</div>
                <div class="req-summary-meta">{{ $requisition->department->name ?? 'N/A' }} · {{ optional($requisition->requested_at)->format('M d, Y') ?: 'No date' }}</div>
            </div>
            <div class="req-summary-spacer"></div>
            @if($requisition->status === 'issued')
            <a href="{{ route('requisitions.receipt', $requisition) }}" class="btn-soft small-btn"><i class="bi bi-receipt"></i> Digital receipt</a>
            @endif
            <span class="status {{ str_contains($requisition->status,'approved') ? 'approved' : ($requisition->status === 'rejected' ? 'low' : 'pending') }}">{{ $requisition->statusLabel() }}</span>
        </div>

        @if(auth()->user()->isSuperAdmin())
        <form method="POST" action="{{ route('requisitions.destroy', $requisition) }}" class="mb-2" data-confirm="Delete this requisition permanently? This cannot be undone.">
            @csrf @method('DELETE')
            <button class="btn-soft small-btn text-danger"><i class="bi bi-trash"></i> Delete Requisition (Super Admin)</button>
        </form>
        @endif

        <table class="kv-table">
            <tr><th><i class="bi bi-signpost-2 me-1"></i>Branch</th><td>{{ $requisition->branch ?: 'NU Clark' }}</td></tr>
            <tr><th><i class="bi bi-building me-1"></i>Department</th><td>{{ $requisition->department->name ?? 'N/A' }}</td></tr>
            <tr><th><i class="bi bi-upc-scan me-1"></i>CSF No.</th><td>{{ $requisition->csf_no ?: 'N/A' }}</td></tr>
            <tr><th><i class="bi bi-person me-1"></i>Requested By</th><td>{{ $requisition->requested_by_name ?: ($requisition->user->name ?? 'N/A') }}</td></tr>
            <tr><th><i class="bi bi-calendar-event me-1"></i>Date Requested</th><td>{{ optional($requisition->requested_at)->format('Y-m-d H:i') ?: 'N/A' }}</td></tr>
            <tr><th><i class="bi bi-card-text me-1"></i>Purpose</th><td class="kv-wide">{{ $requisition->purpose ?: 'N/A' }}</td></tr>
        </table>

        <div class="subhead"><h3 class="subhead-title"><i class="bi bi-list-ul"></i> Requested Items</h3></div>
        <div class="table-responsive mb-3">
        <table class="data-table">
            <thead><tr><th>Item</th><th>Requested</th><th>Approved</th><th>Available Stock</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach($requisition->items as $item)
                <tr>
                    <td data-label="Item">{{ $item->item->name ?? 'N/A' }}</td>
                    <td data-label="Requested">{{ $item->quantity_requested }}</td>
                    <td data-label="Approved">{{ $item->quantity_approved ?? '-' }}</td>
                    <td data-label="Available">{{ $item->item->quantity ?? '-' }}</td>
                    <td data-label="Remarks">{{ $item->remarks ?: '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="subhead"><h3 class="subhead-title"><i class="bi bi-diagram-2"></i> Approval Trail</h3></div>
        <ul class="approval-timeline">
            <li class="{{ $requisition->assetReviewer ? 'signed' : 'pending' }}">
                <div class="step-dot"><i class="bi {{ $requisition->assetReviewer ? 'bi-check-lg' : 'bi-hourglass-split' }}"></i></div>
                <div class="step-row">
                    <div>
                        <div class="step-role">Asset Management</div>
                        <div class="step-name">{{ $requisition->assetReviewer->display_name ?? 'Awaiting review' }}</div>
                    </div>
                    <span class="step-meta {{ $requisition->assetReviewer ? 'signed' : 'pending' }}">{{ $requisition->assetReviewer ? 'Reviewed' : 'Pending' }}</span>
                </div>
            </li>
            <li class="{{ $requisition->deanApprover ? 'signed' : 'pending' }}">
                <div class="step-dot"><i class="bi {{ $requisition->deanApprover ? 'bi-check-lg' : 'bi-hourglass-split' }}"></i></div>
                <div class="step-row">
                    <div>
                        <div class="step-role">College Dean</div>
                        <div class="step-name">{{ $requisition->deanApprover->display_name ?? $requisition->selectedDeanApprover->name ?? 'Awaiting approval' }}</div>
                    </div>
                    <span class="step-meta {{ $requisition->deanApprover ? 'signed' : 'pending' }}">{{ $requisition->deanApprover ? 'Approved' : 'Pending' }}</span>
                </div>
            </li>
            <li class="{{ $requisition->executiveApprover ? 'signed' : 'pending' }}">
                <div class="step-dot"><i class="bi {{ $requisition->executiveApprover ? 'bi-check-lg' : 'bi-hourglass-split' }}"></i></div>
                <div class="step-row">
                    <div>
                        <div class="step-role">Executive Director</div>
                        <div class="step-name">{{ $requisition->executiveApprover->display_name ?? $requisition->selectedExecutiveApprover->name ?? 'Awaiting approval' }}</div>
                    </div>
                    <span class="step-meta {{ $requisition->executiveApprover ? 'signed' : 'pending' }}">{{ $requisition->executiveApprover ? 'Approved' : 'Pending' }}</span>
                </div>
            </li>
        </ul>
    </div>

    <div class="surface p-3">
        <div class="action-panel-head">
            <div class="action-panel-ico"><i class="bi bi-ui-checks"></i></div>
            <div>
                <div class="subhead-title" style="margin:0">Actions</div>
                <div class="tiny">Review, forward, or reject this request.</div>
            </div>
        </div>

        @if($requisition->status === 'rejected')
            <div class="alert alert-danger">Rejected: {{ $requisition->rejection_reason }}</div>
        @endif

        @if(auth()->user()->isAdmin() && $requisition->isAwaitingAssetManagement())
            <div class="note-callout mb-3"><i class="bi bi-info-circle"></i><span>You may cut the requested quantity below based on what's actually available in stock. Adjust "Approved Qty" per item, then forward the request.</span></div>
            <form method="POST" action="{{ route('requisitions.approve',$requisition) }}">
                @csrf
                @foreach($requisition->items as $line)
                    <div class="review-item-card">
                        <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $line->id }}">
                        <div class="review-item-head">
                            <div class="review-item-name">{{ $line->item->name }}</div>
                            <div class="chip-row">
                                <span class="chip-mini"><i class="bi bi-cart"></i> Requested {{ $line->quantity_requested }}</span>
                                <span class="chip-mini {{ $line->item->quantity < $line->quantity_requested ? 'chip-warn' : '' }}"><i class="bi bi-box-seam"></i> Available {{ $line->item->quantity }}</span>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Approved Qty</label>
                                <input type="number" name="items[{{ $loop->index }}][quantity_approved]" class="form-control" min="0" max="{{ $line->quantity_requested }}" value="{{ old('items.'.$loop->index.'.quantity_approved', $line->quantity_approved ?? min($line->quantity_requested, $line->item->quantity)) }}" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Remarks <span class="tiny-2">(optional)</span></label>
                                <input type="text" name="items[{{ $loop->index }}][remarks]" class="form-control" value="{{ old('items.'.$loop->index.'.remarks', $line->remarks) }}" placeholder="Example: cut to available stock">
                            </div>
                        </div>
                    </div>
                @endforeach
                <button class="btn-approve w-100 justify-content-center"><i class="bi bi-check-lg"></i> Forward to College Dean</button>
            </form>

            <details class="reject-disclosure">
                <summary><i class="bi bi-x-circle"></i> Reject this request instead</summary>
                <div class="reject-disclosure-body">
                    <form method="POST" action="{{ route('requisitions.reject',$requisition) }}">
                        @csrf
                        <label class="form-label">Rejection Reason</label>
                        <textarea class="form-control mb-2" name="reason" required></textarea>
                        <div class="field-hint mb-2">This will be shown to the requestor, so be specific about what needs to change.</div>
                        <button class="btn-reject w-100 justify-content-center"><i class="bi bi-x-lg"></i> Confirm Rejection</button>
                    </form>
                </div>
            </details>
        @elseif(auth()->user()->isDeanApprover() && $requisition->isAwaitingCollegeDean())
            <form method="POST" action="{{ route('requisitions.approve',$requisition) }}" class="mb-0">@csrf<button class="btn-approve w-100 justify-content-center"><i class="bi bi-check-lg"></i> Approve and Forward to Executive Director</button></form>
            <details class="reject-disclosure">
                <summary><i class="bi bi-x-circle"></i> Reject this request instead</summary>
                <div class="reject-disclosure-body">
                    <form method="POST" action="{{ route('requisitions.reject',$requisition) }}">
                        @csrf
                        <label class="form-label">Rejection Reason</label>
                        <textarea class="form-control mb-2" name="reason" required></textarea>
                        <div class="field-hint mb-2">This will be shown to the requestor, so be specific about what needs to change.</div>
                        <button class="btn-reject w-100 justify-content-center"><i class="bi bi-x-lg"></i> Confirm Rejection</button>
                    </form>
                </div>
            </details>
        @elseif(auth()->user()->isExecutiveApprover() && $requisition->isAwaitingExecutiveDirector())
            <form method="POST" action="{{ route('requisitions.approve',$requisition) }}" class="mb-0">@csrf<button class="btn-approve w-100 justify-content-center"><i class="bi bi-check-lg"></i> Final Approve Requisition</button></form>
            <details class="reject-disclosure">
                <summary><i class="bi bi-x-circle"></i> Reject this request instead</summary>
                <div class="reject-disclosure-body">
                    <form method="POST" action="{{ route('requisitions.reject',$requisition) }}">
                        @csrf
                        <label class="form-label">Rejection Reason</label>
                        <textarea class="form-control mb-2" name="reason" required></textarea>
                        <div class="field-hint mb-2">This will be shown to the requestor, so be specific about what needs to change.</div>
                        <button class="btn-reject w-100 justify-content-center"><i class="bi bi-x-lg"></i> Confirm Rejection</button>
                    </form>
                </div>
            </details>
        @else
            <div class="empty-state"><i class="bi bi-hourglass-split d-block mb-2" style="font-size:22px"></i>No action available for your account at the current stage.</div>
        @endif
    </div>
</div>
@endsection
