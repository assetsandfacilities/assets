@extends('layouts.admin', ['title' => 'QR Scan Flow', 'subtitle' => 'Scan, preview, and verify the matching CAPEX asset record'])
@section('content')

<div class="qr-grid mb-3">
    <div class="scanner-shell">
        <div class="module-head mb-3">
            <div>
                <h2 class="module-title">Live Scanner</h2>
                <div class="module-note">Use a mobile camera or paste a QR value manually. CAPEX labels store the item code for stable matching.</div>
            </div>
        </div>
        <div class="scanner-box mb-3">
            <div id="reader" style="width:100%;min-height:300px"></div>
        </div>
        <form method="GET" action="{{ route('qr.index') }}" class="d-flex gap-2 flex-wrap">
            <input type="text" class="form-control" name="code" id="manualQrCode" placeholder="Enter or scan QR / item code" value="{{ $normalizedCode ?? request('code') }}">
            <button class="btn-primaryx" type="submit"><i class="bi bi-search"></i> Find Asset</button>
        </form>
    </div>

    <div class="scanner-result">
        <div class="module-head mb-3">
            <div>
                <h2 class="module-title">Scan Result</h2>
                <div class="module-note">Matched system record based on QR value</div>
            </div>
        </div>
        @if($selectedItem)
            <div class="mb-2 d-flex gap-2 flex-wrap">
                <span class="code-badge">{{ $selectedItem->item_type }}</span>
                @if($verificationStatus === 'matched')
                    <span class="status approved">Verified</span>
                @elseif($verificationStatus === 'mismatch')
                    <span class="status low">Location Mismatch</span>
                @elseif($verificationStatus === 'no-room')
                    <span class="status pending">No Room Assigned</span>
                @endif
            </div>
            <h4 class="mb-1">{{ $selectedItem->name }}</h4>
            <div class="tiny mb-2">{{ $selectedItem->item_code }} · {{ $selectedItem->category->name ?? 'Uncategorized' }}</div>
            <p class="tiny mb-3">{{ $selectedItem->description ?: 'No description available.' }}</p>
            <div class="row g-2 mb-3">
                <div class="col-6"><div class="report-stat"><div class="tiny-2">Available Qty</div><div class="fw-bold">{{ $selectedItem->quantity }}</div></div></div>
                <div class="col-6"><div class="report-stat"><div class="tiny-2">Asset Tag ID</div><div class="fw-bold">{{ $selectedItem->asset_tag_id }}</div></div></div>
                <div class="col-6"><div class="report-stat"><div class="tiny-2">Date Acquired</div><div class="fw-bold">{{ $selectedItem->date_acquired_label }}</div></div></div>
                <div class="col-6"><div class="report-stat"><div class="tiny-2">Department</div><div class="fw-bold">{{ $selectedItem->department_label }}</div></div></div>
                <div class="col-6"><div class="report-stat"><div class="tiny-2">Asset Type</div><div class="fw-bold">{{ $selectedItem->asset_type_label }}</div></div></div>
                <div class="col-12"><div class="report-stat"><div class="tiny-2">QR Value</div><div class="fw-bold">{{ $selectedItem->qr_value ?: $selectedItem->item_code }}</div></div></div>
                <div class="col-12"><div class="report-stat"><div class="tiny-2">Assigned Room</div><div class="fw-bold">{{ $selectedItem->room_assigned ?: 'Not assigned' }}</div></div></div>
            </div>

            <div class="surface p-3 mb-3" style="border:1px solid #d7d3d3">
                <div class="module-head mb-2">
                    <div>
                        <h2 class="module-title" style="font-size:16px">Asset Location Verification</h2>
                        <div class="module-note">Check whether the scanned asset is currently in its assigned room.</div>
                    </div>
                </div>
                <form method="GET" action="{{ route('qr.index') }}" class="d-flex gap-2 flex-wrap align-items-end">
                    <input type="hidden" name="code" value="{{ $normalizedCode ?? request('code') }}">
                    <div style="flex:1;min-width:220px">
                        <label class="form-label">Current Room / Location</label>
                        <input type="text" class="form-control" name="verify_room" value="{{ $verifiedRoom }}" placeholder="e.g. Room 719">
                    </div>
                    <button class="btn-primaryx" type="submit"><i class="bi bi-check2-circle"></i> Verify Location</button>
                </form>
                @if($verificationMessage)
                    <div class="mt-3 alert {{ $verificationStatus === 'matched' ? 'alert-success' : ($verificationStatus === 'mismatch' ? 'alert-danger' : 'alert-warning') }} mb-0">
                        {{ $verificationMessage }}
                        @if($verificationStatus === 'mismatch')
                            <div class="tiny mt-1">Assigned room: <strong>{{ $selectedItem->room_assigned }}</strong> · Provided room: <strong>{{ $verifiedRoom }}</strong></div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a target="_blank" href="{{ route('qr.show', $selectedItem) }}" class="btn-soft"><i class="bi bi-qr-code"></i> View / Print QR</a>
                <a href="{{ route('items.show', $selectedItem) }}" class="btn-primaryx"><i class="bi bi-box-seam"></i> View Asset</a>
                <a href="{{ route('requisitions.create', ['item_id' => $selectedItem->id]) }}" class="btn-approve"><i class="bi bi-file-earmark-plus"></i> Create Request</a>
            </div>
        @elseif(request('code'))
            <div class="empty-state">No asset matched the scanned code <strong>{{ $normalizedCode ?? request('code') }}</strong>.</div>
        @else
            <div class="empty-state">Start the scanner or enter a QR code to preview the linked asset record.</div>
        @endif
    </div>
</div>

<div class="qr-card">
    <div class="module-head mb-3">
        <div>
            <h2 class="module-title">CAPEX QR Directory</h2>
            <div class="module-note">Quick-access QR labels for existing assets</div>
        </div>
    </div>
    <div class="qr-tiles">
        @foreach($capexItems as $item)
        <div class="qr-tile text-start">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div style="font-weight:700;font-size:12px">{{ $item->name }}</div>
                    <div class="tiny-2">{{ $item->item_code }}</div>
                    <div class="tiny mt-1">Room: {{ $item->room_assigned ?: 'Not assigned' }}</div>
                </div>
                <span class="status available">Active</span>
            </div>
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <a target="_blank" href="{{ route('qr.show', $item) }}" class="btn-soft small-btn"><i class="bi bi-box-arrow-up-right"></i> View / Print QR</a>
                <a href="{{ route('qr.index', ['code' => $item->qr_value ?: $item->item_code]) }}" class="btn-primaryx small-btn"><i class="bi bi-search"></i> Preview</a>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
function onScanSuccess(decodedText) {
    const input = document.getElementById('manualQrCode');
    input.value = decodedText;
    const form = input.closest('form');
    if (form) {
        form.submit();
    }
}
if (window.innerWidth > 767 && document.getElementById('reader')) {
    const qrScanner = new Html5QrcodeScanner('reader', { fps: 10, qrbox: 220 }, false);
    qrScanner.render(onScanSuccess, function(){});
}
</script>
@endpush
