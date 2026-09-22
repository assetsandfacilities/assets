@extends('layouts.admin', ['title' => 'Print QR Labels'])
@section('content')
<div class="module-head">
    <div>
        <h2 class="module-title">QR Labels</h2>
        <div class="module-note">{{ $items->count() }} label{{ $items->count() === 1 ? '' : 's' }} ready to print — each with its own QR payload.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" onclick="window.print()" class="btn-primaryx"><i class="bi bi-printer"></i> Print All</button>
        <a href="{{ url()->previous() }}" class="btn-soft"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div id="print-area" class="qr-label-grid">
    @foreach($items as $labelItem)
        <div class="surface p-3 qr-label-card">
            <div class="text-center">
                <div class="tiny-2 mb-2">CAPEX ASSET QR LABEL</div>
                <h3 class="module-title mb-1" style="font-size:15px;padding-left:0">{{ $labelItem->name }}</h3>
                <div class="tiny mb-2">{{ $labelItem->item_code }} · {{ $labelItem->category->name ?? 'Uncategorized' }}</div>
                <img src="{{ $labelItem->qrUrl }}" alt="QR Code for {{ $labelItem->name }}" style="max-width:200px;width:100%;height:auto">
                <div class="tiny-2 mt-2">{{ $labelItem->qrPayload }}</div>
            </div>
        </div>
    @endforeach
</div>

<style>
.qr-label-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:4px}
.qr-label-card{break-inside:avoid;page-break-inside:avoid}
@media (max-width: 768px){.qr-label-grid{grid-template-columns:1fr 1fr}}
@media (max-width: 480px){.qr-label-grid{grid-template-columns:1fr}}
@media print {
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .qr-label-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .qr-label-card{box-shadow:none!important;border:1px solid #201e1d}
}
</style>
@endsection
