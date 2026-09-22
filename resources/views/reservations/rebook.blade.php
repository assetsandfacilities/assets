@extends('layouts.admin')

@section('content')
@php
    $original = $reservation->original_start_at ?? $reservation->start_at;
    $originalEnd = $reservation->original_end_at ?? $reservation->end_at;
    $originalVenue = $reservation->originalFacility->name ?? $reservation->facility->name ?? 'your venue';
    $bothOffered = $reservation->mayChangeDate() && $reservation->mayChangeVenue();
    $defaultChoice = old('choice', $reservation->mayChangeDate() ? 'date' : 'venue');
@endphp

{{-- What happened, in the requestor's terms. The approvals are called out
     first because that is the thing they will worry about. --}}
<div class="surface p-3 mb-3" style="border-left:4px solid var(--color-accent)">
    <div class="module-head mb-2">
        <div>
            <h2 class="module-title" style="font-size:16px">{{ $reservation->title }}</h2>
            <div class="module-note">Reference {{ $reservation->reservation_no }}</div>
        </div>
        <span class="status pending">{{ $reservation->displayStatus() }}</span>
    </div>

    <table class="kv-table">
        <tr><th>What happened</th><td>The Facilities Management Office had to release your venue for an emergency.</td></tr>
        <tr><th>Reason given</th><td>{{ $reservation->emergency_reason }}</td></tr>
        <tr><th>Cancelled schedule</th><td>
            {{ optional($original)->format('M d, Y h:i A') }} — {{ optional($originalEnd)->format('h:i A') }} at {{ $originalVenue }}
        </td></tr>
        <tr><th>Your approvals</th><td><strong>Unchanged.</strong> Everyone who signed your proposal stays signed — only the schedule needs replacing.</td></tr>
        @if($reservation->rebooking_decision_note)
        <tr><th>Note from the office</th><td>{{ $reservation->rebooking_decision_note }}</td></tr>
        @endif
    </table>
</div>

@if($reservation->awaitingFmoRebooking())
    <div class="surface p-3">
        <div class="note-callout"><i class="bi bi-hourglass-split"></i>
            <div>
                <strong>Waiting for the Facilities Office.</strong>
                You asked for
                {{ optional($reservation->proposed_start_at)->format('M d, Y h:i A') }} —
                {{ optional($reservation->proposed_end_at)->format('h:i A') }}
                at {{ $reservation->proposedFacility->name ?? $reservation->facility->name }}.
                You will be notified here and by email once it is decided.
            </div>
        </div>
    </div>
@elseif($reservation->wasRebooked())
    <div class="surface p-3">
        <div class="note-callout"><i class="bi bi-check-circle"></i>
            <div>
                <strong>Your new schedule is confirmed:</strong>
                {{ optional($reservation->start_at)->format('M d, Y h:i A') }} at {{ $reservation->facility->name }}.
            </div>
        </div>
    </div>
@else
<form method="POST" action="{{ route('reservations.rebook.update', $reservation) }}" class="premium-form" id="rebookForm" data-unsaved-warning>
    @csrf
    <section class="premium-section">
        <div class="premium-section-head">
            <div class="premium-step">01</div>
            <div>
                <h3>Choose what to change</h3>
                <p>
                    @if($bothOffered)
                        You can keep the venue and move the date, or keep the date and move to another venue.
                    @elseif($reservation->mayChangeDate())
                        The office asked you to keep {{ $originalVenue }} and pick a new date.
                    @else
                        The office asked you to keep the same date and pick a different venue.
                    @endif
                </p>
            </div>
        </div>
        <div class="premium-section-body">
            @error('choice')<div class="premium-warning mb-3"><i class="bi bi-exclamation-triangle"></i><div><strong>{{ $message }}</strong></div></div>@enderror

            <div class="premium-routing-grid">
                @if($reservation->mayChangeDate())
                <label class="premium-route-card" style="cursor:pointer">
                    <input type="radio" name="choice" value="date" id="choiceDate" {{ $defaultChoice === 'date' ? 'checked' : '' }}>
                    <div class="premium-route-content">
                        <span class="premium-route-kicker">Option A</span>
                        <strong>Move the date</strong>
                        <p>Keep {{ $originalVenue }} and pick a new date and time.</p>
                    </div>
                </label>
                @endif

                @if($reservation->mayChangeVenue())
                <label class="premium-route-card" style="cursor:pointer">
                    <input type="radio" name="choice" value="venue" id="choiceVenue" {{ $defaultChoice === 'venue' ? 'checked' : '' }}>
                    <div class="premium-route-content">
                        <span class="premium-route-kicker">Option B</span>
                        <strong>Move the venue</strong>
                        <p>Keep {{ optional($original)->format('M d, Y h:i A') }} and pick a different venue.</p>
                    </div>
                </label>
                @endif
            </div>
        </div>
    </section>

    @if($reservation->mayChangeDate())
    <section class="premium-section" data-panel="date">
        <div class="premium-section-head">
            <div class="premium-step">02</div>
            <div>
                <h3>New date and time</h3>
                <p>{{ $leadNotice ?? 'Pick when the activity should run instead.' }}</p>
            </div>
        </div>
        <div class="premium-section-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Start date and time</label>
                    <input type="datetime-local" name="new_start_at" class="form-control"
                           value="{{ old('new_start_at') }}" min="{{ $minAttribute }}">
                    @error('new_start_at')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">End date and time</label>
                    <input type="datetime-local" name="new_end_at" class="form-control"
                           value="{{ old('new_end_at') }}" min="{{ $minAttribute }}">
                    @error('new_end_at')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>
    @endif

    @if($reservation->mayChangeVenue())
    <section class="premium-section" data-panel="venue">
        <div class="premium-section-head">
            <div class="premium-step">{{ $reservation->mayChangeDate() ? '03' : '02' }}</div>
            <div>
                <h3>Different venue</h3>
                <p>Same schedule: {{ optional($original)->format('M d, Y h:i A') }} — {{ optional($originalEnd)->format('h:i A') }}.</p>
            </div>
        </div>
        <div class="premium-section-body">
            <label class="form-label">Venue</label>
            <select name="new_facility_id" class="form-select">
                <option value="">Select a venue</option>
                @foreach($facilities as $facility)
                    @if((int) $facility->id !== (int) ($reservation->original_facility_id ?? $reservation->facility_id))
                        <option value="{{ $facility->id }}" @selected(old('new_facility_id') == $facility->id)>{{ $facility->name }}</option>
                    @endif
                @endforeach
            </select>
            @error('new_facility_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </section>
    @endif

    <div class="premium-form-actionbar">
        <div class="premium-action-note">
            <i class="bi bi-info-circle"></i>
            Your choice goes back to the Facilities Office for confirmation. No approver has to sign again.
        </div>
        <div class="premium-action-buttons">
            <a href="{{ route('activity-proposals.index') }}" class="btn-soft" data-unsaved-cancel>Cancel</a>
            <button class="btn-primaryx" type="submit"><i class="bi bi-send"></i> Send to Facilities Office</button>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
/* Show only the panel that belongs to the selected option. Both panels stay in
   the DOM so their values survive a validation round-trip. */
(function () {
    var form = document.getElementById('rebookForm');
    if (!form) return;
    var panels = {
        date: form.querySelector('[data-panel="date"]'),
        venue: form.querySelector('[data-panel="venue"]')
    };
    function sync() {
        var picked = form.querySelector('input[name="choice"]:checked');
        var value = picked ? picked.value : null;
        Object.keys(panels).forEach(function (key) {
            if (panels[key]) panels[key].style.display = (value === key) ? '' : 'none';
        });
    }
    form.querySelectorAll('input[name="choice"]').forEach(function (radio) {
        radio.addEventListener('change', sync);
    });
    sync();
})();
</script>
@endpush
