@extends('layouts.admin', ['title' => 'Supplier Management', 'subtitle' => 'Manage vendor information and contacts'])
@section('page-actions')
<a href="{{ route('suppliers.create') }}" class="btn-primaryx"><i class="bi bi-plus-lg"></i> Add Supplier</a>
@endsection
@section('content')
<div class="surface p-3">
    <div class="search-strip" style="max-width:230px"><i class="bi bi-search text-muted"></i><input class="search-input" placeholder="Search suppliers..."></div>
    <div class="grid-cards">
        @forelse($suppliers as $supplier)
        <div class="supplier-card">
            <div style="font-weight:800;font-size:14px">{{ $supplier->name }}</div>
            <div class="supplier-meta">S{{ str_pad($supplier->id, 3, '0', STR_PAD_LEFT) }}</div>
            <div class="supplier-avatar">{{ strtoupper(substr($supplier->contact_person ?: $supplier->name, 0, 1)) }}</div>
            <div style="font-weight:700;font-size:13px">{{ $supplier->contact_person ?: 'Contact Person' }}</div>
            <div class="tiny">Contact Person</div>
            <div class="muted-line"><i class="bi bi-envelope"></i><span>{{ $supplier->email ?: 'No email provided' }}</span></div>
            <div class="muted-line"><i class="bi bi-telephone"></i><span>{{ $supplier->phone ?: 'No phone provided' }}</span></div>
            <div class="muted-line"><i class="bi bi-geo-alt"></i><span>{{ $supplier->address ?: 'Clark Freeport Zone' }}</span></div>
            <hr>
            <div class="tiny-2 mb-1">PRODUCTS</div>
            <span class="tag">Office Supplies</span><span class="tag">Furniture</span>
            <div class="mt-3 d-flex gap-2"><a class="btn-soft small-btn" href="{{ route('suppliers.edit', $supplier) }}"><i class="bi bi-pencil"></i> Edit</a><form method="POST" action="{{ route('suppliers.destroy', $supplier) }}">@csrf @method('DELETE')<button class="btn-soft small-btn"><i class="bi bi-trash"></i></button></form></div>
        </div>
        @empty
        <div class="empty-state" style="grid-column:1/-1">No suppliers found.</div>
        @endforelse
    </div>
    {{ $suppliers->links('vendor.pagination.custom') }}
</div>
@endsection