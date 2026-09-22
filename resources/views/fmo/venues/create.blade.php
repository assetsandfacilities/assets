@extends('layouts.admin', ['title' => 'Add Venue', 'subtitle' => 'New venues appear on the Reservation Request form immediately.'])
@section('content')
<div class="form-shell"><form method="POST" action="{{ route('fmo.venues.store') }}">@csrf @include('fmo.venues.form')<div class="mt-3 d-flex gap-2"><button class="btn-primaryx">Save Venue</button><a class="btn-soft" href="{{ route('fmo.venues.index') }}">Cancel</a></div></form></div>
@endsection
