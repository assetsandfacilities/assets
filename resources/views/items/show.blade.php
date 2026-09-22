@extends('layouts.admin', ['title' => $item->item_type === 'OPEX' ? 'OPEX Item Details' : 'CAPEX Item Details'])
@section('content')
<div class="module-head">
    <div>
        <h2 class="module-title">{{ $item->name }}</h2>
        <div class="module-note">Detailed CAPEX/OPEX inventory record</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(auth()->user()->canManageInventory())
        <a class="btn-primaryx" href="{{ route('items.edit', $item) }}"><i class="bi bi-pencil"></i> Edit Item</a>
        @endif
        @if($item->item_type === 'OPEX' && !auth()->user()->canManageInventory())
        <a class="btn-approve" href="{{ route('requisitions.create', ['item_id' => $item->id]) }}"><i class="bi bi-file-earmark-plus"></i> Create Request</a>
        @endif
    </div>
</div>
<div class="panel-grid-2">
    <div class="surface p-3">
        <img src="{{ $item->display_image }}" alt="{{ $item->name }}" style="width:100%;max-height:320px;object-fit:cover;border-radius:0;border:1px solid var(--line);margin-bottom:16px;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="img-fallback" style="width:100%;height:220px;border-radius:0;margin-bottom:16px;font-size:34px"><i class="bi bi-image"></i></div>
        <h3 class="module-title mb-3" style="font-size:16px">Item Profile</h3>
        <div class="row g-3">
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">{{ $item->item_type === 'CAPEX' ? 'Asset Tag ID' : 'Item Code' }}</div><div class="fw-bold">{{ $item->asset_tag_id }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Type</div><div class="fw-bold">{{ $item->item_type }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Category</div><div class="fw-bold">{{ $item->category->name ?? 'Uncategorized' }}</div></div></div>
            @if($item->item_type === 'CAPEX')
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Date Acquired</div><div class="fw-bold">{{ $item->date_acquired_label }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Department</div><div class="fw-bold">{{ $item->department_label }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Asset Type</div><div class="fw-bold">{{ $item->asset_type_label }}</div></div></div>
            @endif
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Brand</div><div class="fw-bold">{{ $item->brand ?: 'No brand' }}</div></div></div>
            @if($item->item_type !== 'CAPEX')
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Unit</div><div class="fw-bold">{{ $item->unit }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Unit Price</div><div class="fw-bold">₱{{ number_format((float)$item->unit_price, 2) }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Available Quantity</div><div class="fw-bold">{{ $item->quantity }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Availability</div><div class="fw-bold">{{ $item->availability_status }}</div></div></div>
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Low Stock Threshold</div><div class="fw-bold">{{ $item->low_stock_threshold }}</div></div></div>
            @endif
            <div class="col-md-6"><div class="report-stat"><div class="tiny-2">Assigned Room</div><div class="fw-bold">{{ $item->room_assigned ?: 'Not assigned' }}</div></div></div>
        </div>
    </div>
    <div class="surface p-3">
        <h3 class="module-title mb-3" style="font-size:16px">Details</h3>
        <div class="mb-3">
            <label class="form-label">Specifications</label>
            <div class="form-control" style="min-height:96px;background:var(--surface-2)">{{ $item->specifications ?: 'No specifications available.' }}</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <div class="form-control" style="min-height:96px;background:var(--surface-2)">{{ $item->description ?: 'No description available.' }}</div>
        </div>
        <div class="settings-list">
            <div class="settings-item"><h5>Status</h5><p class="tiny mb-0">{{ $item->is_active ? 'This item is active in inventory.' : 'This item is inactive in inventory.' }}</p></div>
            <div class="settings-item"><h5>Request visibility</h5><p class="tiny mb-0">Out of stock OPEX items are hidden from requestor accounts.</p></div>
            <div class="settings-item"><h5>Inventory summary</h5><p class="tiny mb-0">Brand, pricing, specs, and image are now shown for easier identification.</p></div>
        </div>
    </div>
</div>
@endsection
