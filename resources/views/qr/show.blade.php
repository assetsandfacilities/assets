@extends('layouts.admin', ['title' => 'QR Label'])
@section('content')
<div class="module-head">
    <div>
        <h2 class="module-title">QR Label</h2>
        <div class="module-note">Printable QR label for CAPEX item identification</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" onclick="window.print()" class="btn-primaryx"><i class="bi bi-printer"></i> Print QR</button>
        <a href="{{ route('items.show', $item) }}" class="btn-soft"><i class="bi bi-box-seam"></i> View Asset</a>
    </div>
</div>

<div class="surface p-4 qr-print-card" id="print-area">
    <div class="text-center">
        <div class="tiny-2 mb-2">CAPEX ASSET QR LABEL</div>
        <h3 class="module-title mb-1" style="font-size:20px">{{ $item->name }}</h3>
        <div class="tiny mb-3">{{ $item->item_code }} · {{ $item->category->name ?? 'Uncategorized' }}</div>
        <img src="{{ $qrUrl }}" alt="QR Code for {{ $item->name }}" style="max-width:320px;width:100%;height:auto">
        <div class="mt-3">
            <div class="report-stat">
                <div class="tiny-2">QR Payload</div>
                <div class="fw-bold">{{ $qrPayload }}</div>
            </div>
        </div>
        <div class="tiny mt-3">Scan this code in the QR Scanner module to open the matching asset record.</div>
    </div>
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
        border: 1px solid #d7d3d3;
    }
}
.qr-print-card { max-width: 520px; margin: 0 auto; }
</style>
@endsection
