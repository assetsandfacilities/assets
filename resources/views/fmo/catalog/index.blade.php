@extends('layouts.admin', ['title' => $heading, 'subtitle' => 'Anything active here appears as a tick box on the Reservation Request form.'])

@section('content')
@php $isService = $type === 'service'; @endphp

<div class="surface p-3 mb-3">
    <div class="module-head mb-2">
        <div>
            <h2 class="module-title" style="font-size:16px">Add {{ $isService ? 'Service' : 'Item' }}</h2>
            <div class="module-note">Examples: {{ $isService ? 'ITSO Services, Technical Assistance, Audio/Visual Support, Janitors' : 'Tables, Chairs, Speakers, Projector, Extension Cords' }}.</div>
        </div>
    </div>
    <form method="POST" action="{{ route($routeBase . '.store') }}" class="row g-3">@csrf
        <div class="col-md-4">
            <label class="form-label">Name</label>
            <input name="name" class="form-control" value="{{ old('name') }}" required placeholder="{{ $isService ? 'e.g. ITSO Services' : 'e.g. Speaker' }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Unit <span class="tiny text-muted">(optional)</span></label>
            <input name="unit" class="form-control" value="{{ old('unit') }}" placeholder="{{ $isService ? 'personnel' : 'pc' }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Description <span class="tiny text-muted">(optional)</span></label>
            <input name="description" class="form-control" value="{{ old('description') }}">
        </div>
        <div class="col-md-1">
            <label class="form-label">Order</label>
            <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn-primaryx w-100 justify-content-center"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="col-12 d-flex gap-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="allows_quantity" value="1" id="allows_quantity" checked>
                <label class="form-check-label tiny" for="allows_quantity">Requestor can enter a quantity for this</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active_new" checked>
                <label class="form-check-label tiny" for="is_active_new">Active (show on the Reservation form)</label>
            </div>
        </div>
    </form>
</div>

<div class="surface p-3">
    <form method="GET" class="search-strip">
        <i class="bi bi-search"></i>
        <input class="search-input" name="search" value="{{ $search }}" placeholder="Search {{ $isService ? 'services' : 'items' }}...">
        <button class="btn-primaryx" type="submit"><i class="bi bi-funnel"></i> Search</button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Unit</th><th>Quantity Input</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td data-label="Name">
                        <div style="font-weight:700">{{ $record->name }}</div>
                        @if($record->description)<div class="tiny">{{ $record->description }}</div>@endif
                    </td>
                    <td data-label="Unit">{{ $record->unit ?: '—' }}</td>
                    <td data-label="Quantity Input">{{ $record->allows_quantity ? 'Enabled' : 'Disabled' }}</td>
                    <td data-label="Order">{{ $record->sort_order }}</td>
                    <td data-label="Status"><span class="status {{ $record->is_active ? 'approved' : 'low' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td data-label="Actions">
                        <button class="btn-soft small-btn" type="button" data-bs-toggle="collapse" data-bs-target="#edit-item-{{ $record->id }}"><i class="bi bi-pencil-square"></i> Edit</button>
                        <form method="POST" action="{{ route('fmo.catalog.toggle', $record) }}" class="d-inline">@csrf
                            <button class="btn-soft small-btn">{{ $record->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                        @if(auth()->user()->canDeleteFacilityRecords())
                        <form method="POST" action="{{ route('fmo.catalog.destroy', $record) }}" class="d-inline" data-confirm="Remove this from the Reservation form? Existing reservations keep their saved copy.">@csrf @method('DELETE')
                            <button class="btn-reject small-btn"><i class="bi bi-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                <tr class="collapse-row">
                    <td colspan="6" class="p-0 border-0">
                        <div class="collapse" id="edit-item-{{ $record->id }}">
                            <div class="p-3" style="background:var(--surface-2);border-top:1px solid var(--line)">
                                <form method="POST" action="{{ route('fmo.catalog.update', $record) }}" class="row g-3">@csrf @method('PUT')
                                    <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $record->name }}" required></div>
                                    <div class="col-md-2"><label class="form-label">Unit</label><input name="unit" class="form-control" value="{{ $record->unit }}"></div>
                                    <div class="col-md-3"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ $record->description }}"></div>
                                    <div class="col-md-1"><label class="form-label">Order</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ $record->sort_order }}"></div>
                                    <div class="col-md-2 d-flex align-items-end"><button class="btn-primaryx w-100 justify-content-center"><i class="bi bi-save"></i> Save</button></div>
                                    <div class="col-12 d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="allows_quantity" value="1" id="aq-{{ $record->id }}" @checked($record->allows_quantity)>
                                            <label class="form-check-label tiny" for="aq-{{ $record->id }}">Allow quantity input</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia-{{ $record->id }}" @checked($record->is_active)>
                                            <label class="form-check-label tiny" for="ia-{{ $record->id }}">Active</label>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">Nothing here yet. Add your first {{ $isService ? 'service' : 'item' }} above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $records->links('vendor.pagination.custom') }}</div>
</div>
@endsection
