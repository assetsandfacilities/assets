@extends('layouts.admin')

@section('page-actions')
<button type="button" class="btn-primaryx" onclick="window.print()"><i class="bi bi-printer"></i> Print poster</button>
@endsection

@section('content')
@include('partials.registration-qr-poster', [
    'officeName' => 'Facilities Management Office',
    'officeShort' => 'Facilities Management Office',
    'lede' => 'Scan the code to open the registration page for facility reservations and activity proposals.',
    'voucherNote' => null,
    'registrationUrl' => $registrationUrl,
    'qrImageUrl' => $qrImageUrl,
])
@endsection
