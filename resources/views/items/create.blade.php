@extends('layouts.admin', ['title' => ($type ?? request('type', 'CAPEX')) === 'OPEX' ? 'Add OPEX Item' : 'Add CAPEX Item'])
@section('content')
@php
    $resolvedType = $type ?? request('type', 'CAPEX');
    $isCapex = $resolvedType === 'CAPEX';
@endphp

<div class="inventory-studio-page {{ $isCapex ? 'inventory-studio-capex' : 'inventory-studio-opex' }}">
    <div class="inventory-studio-hero">
        <div class="inventory-studio-hero-mark">
            <i class="bi {{ $isCapex ? 'bi-pc-display-horizontal' : 'bi-box-seam' }}"></i>
        </div>
        <div class="inventory-studio-hero-copy">
            <span class="inventory-studio-eyebrow">{{ $isCapex ? 'Asset Management · Capital Assets' : 'Asset Management · Operating Inventory' }}</span>
            <h2>{{ $isCapex ? 'Create New CAPEX Asset Profile' : 'Create New OPEX Stock Profile' }}</h2>
            <p>
                {{ $isCapex
                    ? 'Build a cleaner asset record with grouped system details, clearer location selection, and a more readable data-entry flow.'
                    : 'Create a stock item using a cleaner catalog layout, more visible fillable fields, and easier-to-read inventory controls.' }}
            </p>
        </div>
        <div class="inventory-studio-hero-side">
            <a href="{{ route('items.index', ['type' => $resolvedType]) }}" class="btn-soft inventory-studio-backbtn"><i class="bi bi-arrow-left"></i> Back to list</a>
            <div class="inventory-studio-pillbar">
                @if($isCapex)
                    <span><i class="bi bi-upc-scan"></i> Auto-tag ready</span>
                    <span><i class="bi bi-buildings"></i> Floor &amp; room based</span>
                    <span><i class="bi bi-collection"></i> Multi-unit creation</span>
                @else
                    <span><i class="bi bi-box2-heart"></i> Stock-ready</span>
                    <span><i class="bi bi-cash-coin"></i> Cost-aware</span>
                    <span><i class="bi bi-exclamation-diamond"></i> Low-stock alerts</span>
                @endif
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('items.store') }}" enctype="multipart/form-data" class="inventory-studio-form">
        @csrf
        @include('items.form', ['item' => $item ?? null])
        <div class="inventory-studio-actionbar inventory-studio-actionbar-clean">
            <a href="{{ route('items.index', ['type' => $resolvedType]) }}" class="btn-soft"><i class="bi bi-x-lg"></i> Cancel</a>
            <button class="btn-primaryx"><i class="bi bi-check2-circle"></i> Save {{ $isCapex ? 'CAPEX Asset' : 'OPEX Item' }}</button>
        </div>
    </form>
</div>
@endsection
