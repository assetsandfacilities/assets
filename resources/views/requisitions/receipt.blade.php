@extends('layouts.admin', ['title' => 'Issuance Receipt'])
@section('content')
@php
    $totalAmount = $requisition->items->sum(fn($line) => (float) ($line->total_amount ?? 0));
    $signatures = [
        ['role' => 'Reviewed By — Asset Management', 'user' => $requisition->assetReviewer, 'at' => $requisition->asset_reviewed_at],
        ['role' => 'Checked / Approved By — College Dean', 'user' => $requisition->deanApprover ?: $requisition->selectedDeanApprover, 'at' => $requisition->dean_approved_at],
        ['role' => 'Final Approved By — Executive Director', 'user' => $requisition->executiveApprover ?: $requisition->selectedExecutiveApprover, 'at' => $requisition->executive_approved_at],
    ];
@endphp
<div class="module-head">
    <div>
        <h2 class="module-title">Issuance Receipt</h2>
        <div class="module-note">Complete OPEX / Asset Management charge slip with approval e-signatures.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        {{-- Panel revision: printing is no longer part of releasing. This page is
             the on-screen record the Asset Management admin checks before handing
             over the supplies; the issuance log is what records accountability. --}}
        <a href="{{ route('requisitions.show', $requisition) }}" class="btn-soft"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="surface p-4 receipt-print-card" id="print-area">
    <div class="receipt-header">
        <div>
            <div class="tiny-2">NATIONAL UNIVERSITY — CLARK</div>
            <h3 class="receipt-title">Asset Management / OPEX Issuance Receipt</h3>
            <div class="tiny">Charge Slip and Digital Approval Record</div>
        </div>
        <div class="receipt-ref">
            <div class="tiny-2">REFERENCE NO.</div>
            <strong>{{ $requisition->requisition_no }}</strong>
        </div>
    </div>

    <div class="receipt-section-title">Request Details</div>
    <table class="receipt-kv">
        <tr><th>Branch</th><td>{{ $requisition->branch ?: 'NU Clark' }}</td><th>Department</th><td>{{ $requisition->department->name ?? 'N/A' }}</td></tr>
        <tr><th>CSF No.</th><td colspan="3">{{ $requisition->csf_no ?: 'N/A' }}</td></tr>
        <tr><th>Requested By</th><td>{{ $requisition->requested_by_name ?: ($requisition->user->name ?? 'N/A') }}</td><th>Date Requested</th><td>{{ optional($requisition->requested_at)->format('m/d/Y h:i A') ?: 'N/A' }}</td></tr>
        <tr><th>Selected Dean</th><td>{{ $requisition->selectedDeanApprover->display_name ?? $requisition->checked_by_name ?? 'N/A' }}</td><th>Selected Executive</th><td>{{ $requisition->selectedExecutiveApprover->display_name ?? $requisition->approved_by_name ?? 'N/A' }}</td></tr>
        <tr><th>Purpose</th><td colspan="3">{{ $requisition->purpose ?: 'N/A' }}</td></tr>
    </table>

    <div class="receipt-section-title">Issued Items</div>
    <table class="receipt-items">
        <thead>
            <tr><th>#</th><th>Item</th><th>Unit</th><th class="num">Requested</th><th class="num">Approved / Issued</th><th class="num">Unit Cost</th><th class="num">Amount</th><th>Remarks</th></tr>
        </thead>
        <tbody>
            @foreach($requisition->items as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $line->item->name ?? 'N/A' }}</td>
                <td>{{ $line->item->unit ?? '-' }}</td>
                <td class="num">{{ $line->quantity_requested }}</td>
                <td class="num">{{ $line->quantity_approved ?? $line->quantity_requested }}</td>
                <td class="num">₱{{ number_format((float) ($line->unit_price ?? 0), 2) }}</td>
                <td class="num">₱{{ number_format((float) ($line->total_amount ?? 0), 2) }}</td>
                <td>{{ $line->remarks ?: '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="6" class="num"><strong>Total Requested Value</strong></td><td class="num"><strong>₱{{ number_format($totalAmount, 2) }}</strong></td><td></td></tr>
        </tfoot>
    </table>

    <div class="receipt-section-title">Issuance Details</div>
    <table class="receipt-kv">
        <tr><th>Date Issued</th><td>{{ optional($requisition->issuance->issued_at)->format('m/d/Y h:i A') ?: 'N/A' }}</td><th>Issued By</th><td>{{ $requisition->issuance->issuer->name ?? 'N/A' }}</td></tr>
        <tr><th>Received By</th><td>{{ $requisition->issuance->receiver->name ?? 'N/A' }}</td><th>Issuance Status</th><td>{{ ucwords(str_replace('_', ' ', $requisition->issuance->status ?? 'issued')) }}</td></tr>
        @if($requisition->issuance->remarks)
        <tr><th>Issuance Remarks</th><td colspan="3">{{ $requisition->issuance->remarks }}</td></tr>
        @endif
    </table>

    <div class="receipt-section-title">Digital Approval Signatures</div>
    <div class="signature-grid-print">
        @foreach($signatures as $signature)
        <div class="signature-card-print">
            <div class="signature-role-print">{{ $signature['role'] }}</div>
            <div class="signature-image-print">
                @if($signature['user']?->signature_data)
                    <img src="{{ $signature['user']->signature_data }}" alt="E-signature of {{ $signature['user']->name }}">
                @else
                    <span>No e-signature on file</span>
                @endif
            </div>
            <div class="signature-name-print">{{ $signature['user']->display_name ?? 'N/A' }}</div>
            <div class="tiny-2">Printed Name</div>
            <div class="signature-time-print">{{ optional($signature['at'])->format('m/d/Y h:i A') ?: 'Approval timestamp unavailable' }}</div>
        </div>
        @endforeach
    </div>

    <div class="receipt-footnote">This system-generated document includes the e-signatures and printed names recorded in the approval trail for this requisition.</div>
</div>

<style>
.receipt-print-card{max-width:1000px;margin:0 auto;background:#f3f2f2;color:#201e1d}
.receipt-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;border-bottom:2px solid #201e1d;padding-bottom:12px;margin-bottom:16px}
.receipt-title{font-size:20px;font-weight:800;margin:2px 0}
.receipt-ref{text-align:right;border:1px solid #bab6b6;border-radius:0;padding:8px 12px;min-width:190px}
.receipt-section-title{font-size:12px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;margin:18px 0 7px;padding-bottom:4px;border-bottom:1px solid #bab6b6}
.receipt-kv,.receipt-items{width:100%;border-collapse:collapse;font-size:12px}
.receipt-kv th,.receipt-kv td,.receipt-items th,.receipt-items td{border:1px solid #bab6b6;padding:7px 8px;vertical-align:top}
.receipt-kv th{background:#f3f2f2;width:16%;font-weight:800}
.receipt-items thead th{background:#eae9e9;font-weight:800}
.receipt-items .num{text-align:right;white-space:nowrap}
.signature-grid-print{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.signature-card-print{border:1px solid #bab6b6;border-radius:0;padding:10px;text-align:center;break-inside:avoid}
.signature-role-print{font-size:10px;font-weight:800;text-transform:uppercase;min-height:30px}
.signature-image-print{height:74px;display:flex;align-items:center;justify-content:center;border-bottom:1px solid #201e1d;margin:5px 8px 6px;color:#605d5d;font-size:10px}
.signature-image-print img{max-width:100%;max-height:68px;object-fit:contain}
.signature-name-print{font-size:12px;font-weight:800;margin-top:2px}
.signature-time-print{font-size:9px;color:#444141;margin-top:5px}
.receipt-footnote{text-align:center;font-size:9px;color:#444141;margin-top:18px;padding-top:8px;border-top:1px dashed #9b9797}
@media (max-width: 800px){.signature-grid-print{grid-template-columns:1fr}.receipt-header{flex-direction:column}.receipt-ref{text-align:left}}
@media print {
    @page{size:A4 portrait;margin:10mm}
    body *{visibility:hidden !important}
    #print-area,#print-area *{visibility:visible !important}
    #print-area{position:absolute;left:0;top:0;width:100%;max-width:none;margin:0;padding:0!important;box-shadow:none!important;border:0!important}
    .signature-grid-print{grid-template-columns:repeat(3,1fr)}
    .receipt-kv,.receipt-items{font-size:10px}
    .receipt-kv th,.receipt-kv td,.receipt-items th,.receipt-items td{padding:5px}
}
</style>
@endsection
