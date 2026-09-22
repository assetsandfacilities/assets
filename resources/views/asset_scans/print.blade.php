@extends('layouts.admin', ['title' => 'Asset Scan Report'])
@section('content')
<div class="module-head">
    <div>
        <h2 class="module-title">Asset Scan Report</h2>
        <div class="module-note">Printable record of scanned CAPEX assets, based on the filters currently applied.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" onclick="window.print()" class="btn-primaryx"><i class="bi bi-printer"></i> Print</button>
        <a href="{{ route('asset-scans.index', request()->query()) }}" class="btn-soft"><i class="bi bi-arrow-left"></i> Back to Monitoring</a>
    </div>
</div>

<div class="surface p-4" id="print-area">
    <div class="text-center mb-3">
        <div class="tiny-2 mb-1">NU CLARK ASSET MANAGEMENT — SCAN REPORT</div>
        <div class="tiny">
            Filter: {{ ucfirst($filter) }}
            @if($floorFilter)&middot; Floor: {{ $floors->firstWhere('id', (int) $floorFilter)->name ?? $floorFilter }}@endif
            @if($roomFilter)&middot; Room: {{ $roomFilter }}@endif
        </div>
        <div class="tiny">Printed {{ $printedAt->format('M d, Y h:i A') }} by {{ $printedBy->name }}</div>
    </div>

    <table class="data-table" style="width:100%">
        <thead>
            <tr><th>Date</th><th>Asset</th><th>Expected Room</th><th>Scanned Room</th><th>Status</th><th>Scanned By</th><th>Notes</th></tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                <td>{{ $log->item->item_code ?? 'N/A' }} — {{ $log->item->name ?? '' }}</td>
                <td>{{ $log->expected_room ?: 'N/A' }}</td>
                <td>{{ $log->scanned_room ?: 'N/A' }}</td>
                <td>{{ $log->status === 'matched' ? 'Matched' : ($log->resolved_at ? 'Mismatch (Resolved)' : 'Mismatch (Open)') }}</td>
                <td>{{ $log->user->name ?? 'System' }}</td>
                <td>{{ $log->notes }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">No scan logs match this filter.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="tiny mt-3">Total records: {{ $logs->count() }}</div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow:none!important;
        border: none !important;
    }
}
</style>
@endsection
