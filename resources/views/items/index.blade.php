@extends('layouts.admin', ['title' => $type === 'OPEX' ? 'OPEX Inventory' : 'CAPEX Inventory', 'subtitle' => $type === 'OPEX' ? 'Track supplies and stock levels for request processing' : 'Inventory monitoring only for NU Clark CAPEX assets'])
@php
    $isOpex = $type === 'OPEX';
    $canManage = auth()->user()->canManageInventory();
@endphp
@if($canManage)
@section('page-actions')
<a href="{{ route('items.create', ['type' => $type]) }}" class="btn-primaryx"><i class="bi bi-plus-lg"></i> {{ $isOpex ? 'Add OPEX Item' : 'Add CAPEX Item' }}</a>
@endsection
@endif
@section('content')
<div class="surface p-3">
    <form method="GET" class="search-strip mb-3">
        <input type="hidden" name="type" value="{{ $type }}">
        <i class="bi bi-search text-muted"></i>
        <input class="search-input" name="search" value="{{ $search ?? '' }}" placeholder="{{ $isOpex ? 'Search by item name, code, brand, or specs...' : 'Search by asset name, code, or room...' }}">
        <div class="filter-box"><i class="bi bi-funnel text-muted"></i><select name="stock_filter" onchange="this.form.submit()"><option value="">All</option>@if($isOpex)<option value="available" @selected(($stockFilter ?? '') === 'available')>Available</option><option value="low" @selected(($stockFilter ?? '') === 'low')>Limited Stock</option>@if($canManage)<option value="out" @selected(($stockFilter ?? '') === 'out')>Out of Stock</option>@endif @else<option value="active" @selected(($stockFilter ?? '') === 'active')>Active Only</option>@endif</select></div>
        @if(!$isOpex)
        <div class="filter-box"><i class="bi bi-layers-half text-muted"></i><select name="floor_id" onchange="this.form.submit()">
            <option value="">All Floors</option>
            @foreach($floors as $floorOption)<option value="{{ $floorOption->id }}" @selected(($floorFilter ?? '') == $floorOption->id)>{{ $floorOption->name }}</option>@endforeach
        </select></div>
        <div class="filter-box"><i class="bi bi-door-open text-muted"></i><select name="room_id" onchange="this.form.submit()">
            <option value="">All Rooms</option>
            @foreach($rooms as $roomOption)<option value="{{ $roomOption->id }}" @selected(($roomFilter ?? '') == $roomOption->id)>{{ $roomOption->name }}</option>@endforeach
        </select></div>
        @endif
        <button class="btn-primaryx small-btn" type="submit">Apply</button>
    </form>
    <div class="table-responsive">
        <div class="rec-list">
                @forelse($items as $item)
                    <article class="rec">
                        <div class="rec-media">
                            <img src="{{ $item->display_image }}" alt="{{ $item->name }}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="img-fallback"><i class="bi bi-image"></i></div>
                        </div>
                        <div class="rec-body">
                            <div class="rec-kicker">{{ $item->item_code }} &middot; {{ $isOpex ? 'OPEX' : 'CAPEX' }}</div>
                            <h3 class="rec-heading"><a href="{{ route('items.show', $item) }}">{{ $item->name }}</a></h3>
                            <p class="rec-desc">{{ $item->specifications ?: ($item->description ?: 'No description recorded.') }}</p>
                            <dl class="rec-facts">
                                <div><dt>{{ $isOpex ? 'Category / Brand' : 'Category' }}</dt><dd>{{ $item->category->name ?? 'Uncategorized' }}<span>{{ $item->brand ?: 'No brand' }}</span></dd></div>
                                <div><dt>{{ $isOpex ? 'Unit price' : 'QR / Code' }}</dt><dd>{{ $isOpex ? '₱'.number_format((float) $item->unit_price, 2) : ($item->qr_value ?: $item->item_code) }}<span>{{ $isOpex ? 'per '.$item->unit : 'Scan value' }}</span></dd></div>
                                @if($isOpex)
                                    <div><dt>Stock</dt><dd>{{ $item->quantity }} {{ $item->unit }}<span>Threshold: {{ $item->low_stock_threshold }}</span></dd></div>
                                @else
                                    <div><dt>Assigned room</dt><dd>{{ $item->room?->name ?? 'Not assigned' }}<span>{{ $item->room?->floor?->name ?? 'No floor' }}</span></dd></div>
                                @endif
                            </dl>
                        </div>
                        <div class="rec-side">
                            @if($isOpex)
                                <span class="status {{ $item->isOutOfStock() ? 'maintenance' : ($item->isLimitedStock() ? 'low' : 'available') }}">{{ $item->availability_status }}</span>
                            @else
                                <span class="status {{ $item->is_active ? 'available' : 'maintenance' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span>
                            @endif
                            <div class="rec-actions">
                                <a class="btn-soft small-btn" href="{{ route('items.show', $item) }}"><i class="bi bi-eye"></i> View</a>

                                @if(!$isOpex)
                                    <a class="btn-soft small-btn" href="{{ route('qr.batch', $item) }}"><i class="bi bi-qr-code"></i> QR</a>
                                @endif

                                @if($canManage)
                                    <a class="btn-soft small-btn" href="{{ route('items.edit', $item) }}"><i class="bi bi-pencil"></i> Edit</a>

                                    <form class="d-inline" method="POST" action="{{ route('items.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-soft small-btn" data-confirm="Delete item?"><i class="bi bi-trash"></i> Delete</button>
                                    </form>
                                @elseif($isOpex && auth()->user()->isRequestor())
                                    <a class="btn-approve small-btn" href="{{ route('requisitions.create', ['item_id' => $item->id]) }}"><i class="bi bi-cart-plus"></i> Request</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">No {{ strtolower($isOpex ? 'opex items' : 'capex assets') }} available.</div>
                @endforelse
        </div>
    </div>
    {{ $items->links('vendor.pagination.custom') }}
</div>
@endsection
