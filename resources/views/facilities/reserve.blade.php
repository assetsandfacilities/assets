@extends('layouts.admin', ['title' => 'Reserve Facility'])
@section('content')
<div class="form-shell">
  <div class="module-head"><div><h2 class="module-title">Facility Reservation Form</h2><div class="module-note">The system will block the request when it overlaps with an existing pending or approved schedule.</div></div></div>
  @if(\App\Support\BookingWindow::notice())
  <div class="note-callout mb-3"><i class="bi bi-calendar-check"></i><div>{{ \App\Support\BookingWindow::notice() }}</div></div>
@endif
<form method="POST" action="{{ route('facilities.reservations.store') }}" id="facilityReservationForm" data-unsaved-warning>@csrf
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Facility</label><select name="facility_id" class="form-select" required><option value="">Select facility</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}" @selected(old('facility_id') == $facility->id)>{{ $facility->name }} — {{ $facility->location }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">Activity / Event Title</label><input name="title" class="form-control" value="{{ old('title') }}" required></div>
      <div class="col-md-6"><label class="form-label">Start Date and Time</label><input type="datetime-local" name="start_at" id="facilityStartAt" class="form-control" value="{{ old('start_at') }}" required></div>
      <div class="col-md-6"><label class="form-label">End Date and Time</label><input type="datetime-local" name="end_at" id="facilityEndAt" class="form-control" value="{{ old('end_at') }}" required></div>
      <div class="col-12"><label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="3">{{ old('purpose') }}</textarea></div>

      @include('facilities.partials.requirements-picker')
    </div>
    <div class="premium-form-actionbar mt-4"><div class="premium-action-note"><i class="bi bi-info-circle"></i><span>Your chosen dates stay exactly as entered. The system validates the reservation timeframe before submission.</span></div><div class="premium-action-buttons"><a class="btn-soft" href="{{ route(auth()->user()->homeRouteName()) }}" data-unsaved-cancel>Cancel</a><button class="btn-primaryx" type="submit"><i class="bi bi-send"></i> Submit Reservation</button></div></div>
  </form>
</div>

@php
  $reservationLeadDays = \App\Support\BookingWindow::leadDays();
  $reservationEarliest = \App\Support\BookingWindow::earliestStart();
@endphp
<div class="modal fade reservation-window-modal" id="facilityLeadTimeModal" tabindex="-1" aria-labelledby="facilityLeadTimeModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <div class="reservation-window-icon"><i class="bi bi-calendar-x"></i></div>
          <div><div class="reservation-window-eyebrow">Reservation timeframe</div><h5 class="modal-title" id="facilityLeadTimeModalTitle">Selected date is too soon</h5></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">This request does not meet the minimum reservation lead time configured by Facilities Management.</p>
        <div class="reservation-window-facts">
          <div><span>Minimum notice</span><strong>{{ $reservationLeadDays }} {{ \Illuminate\Support\Str::plural('day', $reservationLeadDays) }}</strong></div>
          <div><span>Earliest allowed date</span><strong>{{ $reservationEarliest->format('F j, Y') }}</strong></div>
        </div>
        <div class="reservation-window-note"><i class="bi bi-info-circle"></i><span>The system will not replace your chosen date automatically. Select a valid date before submitting.</span></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn-primaryx" data-bs-dismiss="modal">Choose Another Date</button></div>
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('facilityReservationForm');
  const start = document.getElementById('facilityStartAt');
  const end = document.getElementById('facilityEndAt');
  const earliest = '{{ $reservationEarliest->format('Y-m-d') }}';
  let lastWarningValue = null;

  function invalidLeadTime() {
    const selected = start.value ? start.value.slice(0, 10) : '';
    return selected && selected < earliest;
  }
  function showWarning(force) {
    if (!invalidLeadTime()) {
      start.classList.remove('is-invalid');
      lastWarningValue = null;
      return false;
    }
    start.classList.add('is-invalid');
    if (force || lastWarningValue !== start.value) {
      lastWarningValue = start.value;
      const el = document.getElementById('facilityLeadTimeModal');
      if (window.bootstrap && el) bootstrap.Modal.getOrCreateInstance(el).show();
    }
    return true;
  }
  function syncEndMin() {
    if (start.value) end.min = start.value; else end.removeAttribute('min');
  }
  start.addEventListener('input', function () { syncEndMin(); showWarning(false); });
  start.addEventListener('change', function () { syncEndMin(); showWarning(false); });
  form.addEventListener('submit', function (event) {
    if (showWarning(true)) {
      event.preventDefault();
      start.focus();
    }
  });
  syncEndMin();
})();
</script>
@endsection
