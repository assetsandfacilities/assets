@extends('layouts.admin')

@section('page-actions')
<a href="{{ route('access-vouchers.index') }}" class="btn-soft"><i class="bi bi-ticket-perforated"></i> Access vouchers</a>
<button type="button" class="btn-primaryx" onclick="window.print()"><i class="bi bi-printer"></i> Print poster</button>
@endsection

@section('content')
@include('partials.registration-qr-poster', [
    'officeName' => 'Asset Management Office',
    'officeShort' => 'Asset Management Office',
    'lede' => 'Scan the code to open the registration page for supply requests and charge slips.',
    'voucherNote' => 'Requestor and approver accounts also need a voucher code issued by this office.',
    'registrationUrl' => $registrationUrl,
    'qrImageUrl' => $qrImageUrl,
])
@endsection
