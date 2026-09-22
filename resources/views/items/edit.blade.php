@extends('layouts.admin', ['title' => $item->item_type === 'OPEX' ? 'Edit OPEX Item' : 'Edit CAPEX Item'])
@section('content')
<div class="module-head">
    <div>
        <h2 class="module-title">{{ $item->item_type === 'OPEX' ? 'Edit OPEX Item' : 'Edit CAPEX Item' }}</h2>
        <div class="module-note">Update item details, stock, and classification.</div>
    </div>
    <a href="{{ route('items.index', ['type' => $item->item_type]) }}" class="btn-soft small-btn"><i class="bi bi-arrow-left"></i> Back to list</a>
</div>

<form method="POST" action="{{ route('items.update',$item) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('items.form')
    <div class="form-actionbar">
        <button class="btn-primaryx"><i class="bi bi-check2"></i> Update Item</button>
        <a href="{{ route('items.index', ['type' => $item->item_type]) }}" class="btn btn-light small-btn" style="border:1px solid #bab6b6">Cancel</a>
    </div>
</form>
@endsection
