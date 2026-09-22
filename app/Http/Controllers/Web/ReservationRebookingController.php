<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityReservation;
use App\Notifications\ReservationEmergencyNotification;
use App\Models\User;
use App\Support\BookingWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 | The requestor's half of an emergency cancellation.
 |
 | After the Facilities Office releases a reserved venue, the requestor is
 | asked one question: keep the venue and move the date, or keep the date and
 | move to another venue. Whichever they pick goes back to the office as a
 | proposal -- it is not applied on its own, because the new slot still has to
 | be free.
 |
 | Nothing here writes to an approval column. Any approval signatures already recorded belong to the activity rather than
 | the timeslot, so they stay exactly as they were through the exchange. If the
 | route is still pending, it can continue after the replacement slot is set.
 */
class ReservationRebookingController extends Controller
{
    private function safeNotify($notifiable, $notification): void
    {
        if (!$notifiable) {
            return;
        }
        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Only the owner of the booking answers for it. */
    private function guard(FacilityReservation $reservation): void
    {
        abort_unless((int) $reservation->user_id === (int) auth()->id(), 403,
            'This booking belongs to another requestor.');
        abort_unless($reservation->isEmergencyCancelled(), 404,
            'This booking has not been cancelled, so there is nothing to reschedule.');
    }

    public function edit(FacilityReservation $reservation): View
    {
        $this->guard($reservation);

        return view('reservations.rebook', [
            'title' => 'Choose a new schedule',
            'subtitle' => 'Your venue was released for an emergency. Any approvals already recorded stay in place — pick a replacement and the Facilities Office will confirm it.',
            'reservation' => $reservation->load(['facility', 'originalFacility', 'emergencyCanceller', 'activityProposal']),
            'facilities' => Facility::where('is_active', true)->orderBy('name')->get(),
            'earliestStart' => BookingWindow::earliestStart(),
            'minAttribute' => BookingWindow::minAttribute(),
            'leadNotice' => BookingWindow::notice(),
        ]);
    }

    public function update(Request $request, FacilityReservation $reservation): RedirectResponse
    {
        $this->guard($reservation);

        abort_unless($reservation->awaitingRequestorChoice(), 403,
            'This booking is not waiting for your choice right now.');

        $data = $request->validate([
            'choice' => ['required', 'in:date,venue'],
            'new_start_at' => ['required_if:choice,date', 'nullable', 'date', BookingWindow::startRule()],
            'new_end_at' => ['required_if:choice,date', 'nullable', 'date', 'after:new_start_at'],
            'new_facility_id' => ['required_if:choice,venue', 'nullable', 'exists:facilities,id'],
        ], [
            'new_start_at.after_or_equal' => BookingWindow::violationMessage(),
            'new_start_at.required_if' => 'Choose the new date and time for your activity.',
            'new_end_at.required_if' => 'Choose when the activity ends.',
            'new_end_at.after' => 'The end time has to be after the start time.',
            'new_facility_id.required_if' => 'Choose the venue you want to move to.',
        ]);

        // The office decides which of the two options it can offer; a form that
        // posts the other one anyway is refused here.
        if ($data['choice'] === 'date' && !$reservation->mayChangeDate()) {
            return back()->withErrors(['choice' => 'Facilities Management asked you to choose a different venue for the same date.'])->withInput();
        }
        if ($data['choice'] === 'venue' && !$reservation->mayChangeVenue()) {
            return back()->withErrors(['choice' => 'Facilities Management asked you to choose a new date for the same venue.'])->withInput();
        }

        if ($data['choice'] === 'date') {
            $start = \Carbon\Carbon::parse($data['new_start_at']);
            $end = \Carbon\Carbon::parse($data['new_end_at']);
            $facilityId = $reservation->facility_id;
            $summary = 'New schedule requested: '.$start->format('M d, Y h:i A').' — '.$end->format('h:i A')
                .' at '.($reservation->facility->name ?? 'the same venue');
        } else {
            // Same calendar slot, different room.
            $start = $reservation->original_start_at ?? $reservation->start_at;
            $end = $reservation->original_end_at ?? $reservation->end_at;
            $facilityId = (int) $data['new_facility_id'];
            $facilityName = Facility::find($facilityId)?->name ?? 'another venue';
            $summary = 'Venue change requested: '.$facilityName.' on '.optional($start)->format('M d, Y h:i A');
        }

        /*
         | A quick check so the requestor is told immediately when the slot is
         | plainly taken. The binding check still runs when the office approves,
         | because anything can be booked in between.
         */
        $taken = FacilityReservation::where('facility_id', $facilityId)
            ->where('id', '!=', $reservation->id)
            ->whereIn('status', ['pending', 'approved'])
            ->holdingSlot()
            ->whereDate('start_at', '<=', $end->toDateString())
            ->whereDate('end_at', '>=', $start->toDateString())
            ->where(function ($q) {
                $q->where('status', 'approved')
                    ->orWhere(function ($prePlotted) {
                        $prePlotted->where('is_pre_plotted', true)->where('venue_status', 'approved');
                    });
            })
            ->exists();

        if ($taken) {
            return back()->withErrors([
                'choice' => 'That venue is already confirmed for that date. Please pick another slot.',
            ])->withInput();
        }

        $reservation->update([
            'proposed_kind' => $data['choice'],
            'proposed_start_at' => $start,
            'proposed_end_at' => $end,
            'proposed_facility_id' => $facilityId,
            'proposed_at' => now(),
            'rebooking_status' => FacilityReservation::REBOOK_AWAITING_FMO,
            'rebooking_decision_note' => null,
        ]);

        $fresh = $reservation->fresh(['facility', 'user', 'proposedFacility']);

        // The office is told in-system; no email, since staff work inside the
        // system all day and their queue already shows the item.
        // Officers only: facilitiesSide() also covers student requestors and
        // housekeeping, who have nothing to decide here.
        $officers = User::whereIn('role', ['fmo_super_admin', 'fmo'])
            ->where('is_approved', true)
            ->get();

        foreach ($officers as $officer) {
            $this->safeNotify($officer, new ReservationEmergencyNotification(
                $fresh,
                'A requestor chose a replacement schedule',
                ($fresh->user->name ?? 'A requestor').' answered an emergency cancellation and is waiting for your decision.',
                route('fmo.reservations.show', $fresh),
                'Review the rebooking',
                [$summary],
                false
            ));
        }

        return redirect()
            ->route('activity-proposals.index')
            ->with('success', 'Sent to the Facilities Management Office for confirmation. '.$summary);
    }
}
