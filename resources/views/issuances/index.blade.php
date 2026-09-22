@extends('layouts.admin', ['title' => 'Issuance & Returns', 'subtitle' => 'Process approved requests and mark issued items as returned'])
@section('page-actions')
<a href="{{ route('issuances.create') }}" class="btn-primaryx"><i class="bi bi-plus-lg"></i> New Issuance</a>
@endsection
@section('content')
<div class="page-tabs"><span class="active"><i class="bi bi-arrow-up-right-circle"></i> Issued ({{ $issuances->where('status','issued')->count() }})</span><span><i class="bi bi-arrow-counterclockwise"></i> Returned ({{ $issuances->where('status','returned')->count() }})</span></div>
<div class="surface p-3">
    <div class="search-strip"><i class="bi bi-search text-muted"></i><input class="search-input" placeholder="Search approved requests..."></div>
    @forelse($issuances as $issuance)
    <article class="rec rec--plain">
        <div class="rec-body">
            <div class="rec-kicker">{{ $issuance->requisition->requisition_no ?? 'N/A' }} &middot; {{ $issuance->requisition->department->name ?? 'Department' }}</div>
            <h3 class="rec-heading">{{ $issuance->receiver->name ?? 'N/A' }}</h3>
            <dl class="rec-facts">
                <div><dt>Items to issue</dt><dd class="rec-fact-plain">
                    @forelse($issuance->requisition->items as $reqItem)
                        {{ $reqItem->quantity_requested }}x {{ $reqItem->item->name ?? 'Item' }} <span class="pill-opex">{{ $reqItem->item->item_type ?? 'OPEX' }}</span>@if(!$loop->last), @endif
                    @empty
                        No linked item found.
                    @endforelse
                </dd></div>
            </dl>
        </div>
        <div class="rec-side">
            <span class="status approved">{{ ucfirst($issuance->status) }}</span>
            <div class="rec-actions">
                @if($issuance->status === 'issued')
                    <form method="POST" action="{{ route('issuances.return',$issuance) }}">
                        @csrf
                        <button type="submit" class="btn-primaryx small-btn"><i class="bi bi-arrow-counterclockwise"></i> To Return</button>
                    </form>
                @else
                    <button type="button" class="btn-soft small-btn" disabled>Already Returned</button>
                @endif
                @if(auth()->user()->isSuperAdmin())
                <form method="POST" action="{{ route('issuances.destroy', $issuance) }}" data-confirm="Delete this issuance record permanently? This cannot be undone.">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-soft small-btn text-danger"><i class="bi bi-trash"></i> Delete</button>
                </form>
                @endif
            </div>
        </div>
    </article>
    @empty
    <div class="empty-state">No issuance records found.</div>
    @endforelse
    {{ $issuances->links('vendor.pagination.custom') }}
</div>
@endsection
