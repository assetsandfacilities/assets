@extends('layouts.admin', ['title' => 'Edit Venue'])
@section('content')
<div class="form-shell"><form method="POST" action="{{ route('fmo.venues.update', $facility) }}">@csrf @method('PUT') @include('fmo.venues.form')<div class="mt-3 d-flex gap-2"><button class="btn-primaryx">Update Venue</button><a class="btn-soft" href="{{ route('fmo.venues.index') }}">Cancel</a></div></form></div>
@endsection
