@extends('layouts.admin', ['title' => 'Print QR Labels'])
@section('content')
<div class="module-head">
    <div>
        <h2 class="module-title">Print QR Labels</h2>
        <div class="module-note">{{ $baseName }} — choose which unit(s) to print. Each unit keeps its own distinct QR code.</div>
    </div>
    <a href="{{ route('items.index', ['type' => 'CAPEX']) }}" class="btn-soft small-btn"><i class="bi bi-arrow-left"></i> Back to Assets</a>
</div>

<div class="surface p-3">
    <form method="GET" action="{{ route('qr.print') }}" id="batchPrintForm">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="tiny">{{ $siblings->count() }} unit{{ $siblings->count() === 1 ? '' : 's' }} found in this batch.</div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn-soft small-btn" id="selectAllBtn"><i class="bi bi-check-all"></i> Select All</button>
                <button type="button" class="btn-soft small-btn" id="selectNoneBtn"><i class="bi bi-x-lg"></i> Clear Selection</button>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="data-table">
                <thead><tr><th style="width:40px"></th><th>Unit</th><th>Asset Tag / QR Payload</th><th>Room</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($siblings as $sibling)
                    <tr>
                        <td data-label="Select"><input type="checkbox" class="unit-checkbox form-check-input" name="ids[]" value="{{ $sibling->id }}" {{ $sibling->id === $item->id ? 'checked' : '' }}></td>
                        <td data-label="Unit">{{ $sibling->name }}</td>
                        <td data-label="Asset Tag"><span class="code-badge">{{ $sibling->qr_value ?: $sibling->item_code }}</span></td>
                        <td data-label="Room">{{ $sibling->room_assigned ?: 'Not assigned' }}</td>
                        <td data-label="Status"><span class="status {{ $sibling->is_active ? 'available' : 'maintenance' }}">{{ $sibling->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn-primaryx" id="printSelectedBtn"><i class="bi bi-printer"></i> Print Selected</button>
    </form>
</div>

<script>
(function() {
    const checkboxes = document.querySelectorAll('.unit-checkbox');
    document.getElementById('selectAllBtn').addEventListener('click', () => checkboxes.forEach(cb => cb.checked = true));
    document.getElementById('selectNoneBtn').addEventListener('click', () => checkboxes.forEach(cb => cb.checked = false));
    document.getElementById('batchPrintForm').addEventListener('submit', function(e) {
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        if (!anyChecked) {
            e.preventDefault();
            alert('Select at least one unit to print.');
        }
    });
})();
</script>
@endsection
