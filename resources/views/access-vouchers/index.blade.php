@extends('layouts.admin')
@section('title', 'Access Vouchers')
@php
    $title = 'Access Vouchers';
@endphp
@php
    $subtitle = 'Generate single-use registration vouchers for Asset Management requestors and approvers.';
@endphp
@section('content')
<div class="row g-5">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body pt-4 px-0">
                <div class="module-head"><div><h2 class="module-title">Generate voucher</h2><div class="module-note">Single-use codes for requestor and approver registration.</div></div></div>
                @if(session('generated_voucher'))
                    <div class="alert alert-success">
                        <div class="small fw-semibold mb-1" style="letter-spacing:.14em">COPY THIS CODE NOW</div>
                        <div class="fs-5 fw-bold font-monospace" id="generatedCode">{{ session('generated_voucher') }}</div>
                        <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="navigator.clipboard.writeText(document.getElementById('generatedCode').innerText)">Copy Code</button>
                    </div>
                @endif
                <form method="POST" action="{{ route('access-vouchers.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <select name="voucher_type" id="voucherType" class="form-select" required>
                            <option value="requestor">Requestor</option>
                            <option value="approver">Approver</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="approverTypeWrap">
                        <label class="form-label">Approver Type</label>
                        <select name="approver_type" class="form-select">
                            @foreach($approverTypes as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_',' ', $type)) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">The account creator cannot change this approver type.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department (optional)</label>
                        <select name="department_id" class="form-select">
                            <option value="">Any / select during registration</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expires In</label>
                        <select name="expires_in_hours" class="form-select">
                            <option value="24">24 hours</option>
                            <option value="72">3 days</option>
                            <option value="168">7 days</option>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-lg w-100">Generate single-use voucher</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body pt-4 px-0">
                <div class="module-head"><div><h2 class="module-title">Voucher history</h2><div class="module-note">Codes are shown as hints only. Revoke any code that has not been used.</div></div></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Code</th><th>Type</th><th>Department</th><th>Status</th><th>Expires</th><th></th></tr></thead>
                        <tbody>
                        @forelse($vouchers as $voucher)
                            @php
                                $status = $voucher->used_at ? 'Used' : ($voucher->revoked_at ? 'Revoked' : ($voucher->expires_at->isPast() ? 'Expired' : 'Available'));
                            @endphp
                            <tr>
                                <td class="font-monospace">{{ $voucher->code_hint }}</td>
                                <td>{{ ucfirst($voucher->voucher_type) }}@if($voucher->approver_type)<br><span class="text-muted small">{{ ucwords(str_replace('_',' ', $voucher->approver_type)) }}</span>@endif</td>
                                <td>{{ $voucher->department?->name ?? '—' }}</td>
                                <td><span class="badge text-bg-{{ $status === 'Available' ? 'success' : ($status === 'Used' ? 'secondary' : 'danger') }}">{{ $status }}</span></td>
                                <td class="small">{{ $voucher->expires_at->format('M d, Y g:i A') }}</td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        @if(!$voucher->used_at && !$voucher->revoked_at && $voucher->expires_at->isFuture())
                                            <form method="POST" action="{{ route('access-vouchers.revoke', $voucher) }}" data-confirm="Revoke this voucher?">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                            </form>
                                        @endif
                                        @if(auth()->user()?->isSuperAdmin())
                                            <form method="POST" action="{{ route('access-vouchers.destroy', $voucher) }}" data-confirm="Permanently delete this voucher history? This cannot be undone.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">No vouchers generated yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $vouchers->links('vendor.pagination.custom') }}
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function(){
 const select=document.getElementById('voucherType'), wrap=document.getElementById('approverTypeWrap'), ap=wrap.querySelector('select');
 function sync(){ const yes=select.value==='approver'; wrap.classList.toggle('d-none',!yes); ap.required=yes; }
 select.addEventListener('change',sync); sync();
})();
</script>
@endpush
@endsection
