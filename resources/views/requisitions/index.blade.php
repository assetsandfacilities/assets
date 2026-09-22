@extends('layouts.admin', ['title' => 'Requisitions', 'subtitle' => 'Charge slip requests routed through Asset Management, College Dean, and Executive Director.'])
@if(auth()->user()->isRequestor() || auth()->user()->isAdmin())
@section('page-actions')
<a href="{{ route('requisitions.create') }}" class="btn-primaryx"><i class="bi bi-plus-lg"></i> New Request</a>
@endsection
@endif
@section('content')
@if(auth()->user()->isRequestor() && auth()->user()->department)
<div class="stat-grid mb-3">
    <div class="report-stat"><div class="tiny-2">{{ auth()->user()->department->name }} · Department Budget</div><div class="fw-bold">₱{{ number_format(auth()->user()->department->opex_limit, 2) }}</div></div>
    <div class="report-stat"><div class="tiny-2">OPEX Consumed</div><div class="fw-bold">₱{{ number_format(auth()->user()->department->opexConsumed(), 2) }}</div></div>
    <div class="report-stat"><div class="tiny-2">OPEX Remaining</div><div class="fw-bold" style="color:{{ auth()->user()->department->opexRemaining() <= 0 ? '#dd2b0f' : '#201e1d' }}">₱{{ number_format(auth()->user()->department->opexRemaining(), 2) }}</div></div>
</div>
@endif
<div class="page-tabs"><span class="active">Request Monitoring</span><span>{{ auth()->user()->isAdmin() ? 'Asset Management View' : (auth()->user()->isApprover() ? 'Approver Queue' : 'My Requests') }}</span></div>
<div class="surface p-3">
    <form method="GET" class="search-strip mb-3">
        <i class="bi bi-search text-muted"></i>
        <input class="search-input" name="search" value="{{ $search ?? '' }}" placeholder="Search by reference, requester, or item...">
        <div class="filter-box"><i class="bi bi-funnel text-muted"></i>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @if(auth()->user()->isAdmin())
                <option value="pending_release" @selected(($status ?? '') === 'pending_release')>Ready for release (approved, not yet issued)</option>
                @endif
                @foreach(['pending_asset_management' => 'Asset Management','pending_college_dean' => 'College Dean','pending_executive_director' => 'Executive Director','approved' => 'Approved','partially_approved' => 'Partially Approved','rejected' => 'Rejected'] as $key => $label)
                <option value="{{ $key }}" @selected(($status ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-primaryx small-btn" type="submit">Apply</button>
    </form>
    @forelse($requisitions as $requisition)
    <article class="rec rec--plain">
        <div class="rec-body">
            <div class="rec-kicker">{{ $requisition->requisition_no }} &middot; {{ optional($requisition->requested_at)->format('M d, Y') }}</div>
            <h3 class="rec-heading"><a href="{{ route('requisitions.show', $requisition) }}">{{ $requisition->department->name ?? 'No Department' }}</a></h3>
            <p class="rec-desc">{{ $requisition->purpose ?: 'No purpose stated.' }}</p>
            <dl class="rec-facts">
                <div><dt>Requested by</dt><dd>{{ $requisition->user->name ?? 'Unknown User' }}<span>{{ $requisition->branch ?: 'NU Clark' }}</span></dd></div>
                <div><dt>Charged to budget</dt><dd>&#8369;{{ number_format($requisition->items->sum('total_amount'), 2) }}<span>{{ $requisition->items->count() }} {{ \Illuminate\Support\Str::plural('line item', $requisition->items->count()) }}</span></dd></div>
                <div><dt>Items</dt><dd class="rec-fact-plain">{{ $requisition->items->map(fn($line) => ($line->item->name ?? 'Item').' x'.$line->quantity_requested)->join(', ') ?: '—' }}</dd></div>
            </dl>
            @if($requisition->status === 'rejected' && $requisition->rejection_reason)
            <p class="rec-flag"><strong>Reason:</strong> {{ $requisition->rejection_reason }}</p>
            @endif
        </div>
        <div class="rec-side">
            <span class="status {{ str_contains($requisition->status,'approved') ? 'approved' : ($requisition->status === 'rejected' ? 'low' : 'pending') }}">{{ $requisition->statusLabel() }}</span>
            <div class="rec-actions">
                <a class="btn-approve small-btn" href="{{ route('requisitions.show', $requisition) }}"><i class="bi bi-eye"></i> View</a>
            </div>
        </div>
    </article>
    @empty
    <div class="empty-state">No requisitions found.</div>
    @endforelse
    {{ $requisitions->links('vendor.pagination.custom') }}
</div>
@endsection
