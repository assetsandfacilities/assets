<?php
namespace App\Http\Controllers\Web;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityItem;
use App\Models\FacilityReservation;
use App\Notifications\FacilityReservationStatusNotification;
use App\Support\FacilityRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reservation submission side of Facilities.
 *
 * Venue / item / service maintenance and the FMO reservation queue now live in
 * App\Http\Controllers\Web\Fmo\* so they can be locked behind the FMO
 * middleware. What is left here is the part every signed-in requestor uses:
 * filling in and submitting a reservation, plus the approve/reject actions
 * (still routed, still FMO-gated, so any existing bookmark keeps working).
 */
class FacilityController extends Controller
{
    /**
     * Notifications are a side effect, not the reservation record itself -- if
     * mail fails the reservation must still be considered saved, so every
     * notify() call is isolated in its own try/catch.
     */
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

    /**
     * Legacy /facilities entry point. Facilities staff land on the new FMO
     * Reservation Requests screen; everybody else goes to their own home page
     * rather than hitting a 403 from an old notification link.
     */
    public function index()
    {
        $user = auth()->user();

        if ($user->canManageFacilities()) {
            return redirect()->route('fmo.reservations.index');
        }

        return redirect()->route($user->homeRouteName());
    }

    public function createReservation()
    {
        $facilities = Facility::where('is_active', true)->orderBy('name')->get();
        $catalogItems = FacilityItem::active()->items()->ordered()->get();
        $catalogServices = FacilityItem::active()->services()->ordered()->get();

        return view('facilities.reserve', compact('facilities', 'catalogItems', 'catalogServices'));
    }

    public function storeReservation(Request $request)
    {
        $data = $request->validate([
            'facility_id' => ['required','exists:facilities,id'],
            'title' => ['required','string','max:150'],
            'purpose' => ['nullable','string'],
            // Same advance-notice rule as the Activity Proposal form: a rule
            // enforced on one booking path only is not enforced at all.
            'start_at' => ['required','date', \App\Support\BookingWindow::startRule()],
            'end_at' => ['required','date','after:start_at'],
            'requirements' => ['nullable','array'],
            'requirements_other_note' => ['nullable','string','max:1000'],
        ], [
            'start_at.after_or_equal' => \App\Support\BookingWindow::violationMessage(),
        ]);

        $requirements = FacilityRequirements::fromRequest($request);

        // Pre-plotting is based on venue + DATE only. A later request for the
        // same venue on any shared calendar date becomes pre-plotted even when
        // its start/end times do not overlap the earlier request.
        $requestStartDate = Carbon::parse($data['start_at'])->toDateString();
        $requestEndDate = Carbon::parse($data['end_at'])->toDateString();
        $hasPriorHold = FacilityReservation::where('facility_id', $data['facility_id'])
            ->whereIn('status', ['pending','approved'])
            // An emergency-cancelled booking has released its date, so it no
            // longer makes a later request pre-plotted against it.
            ->holdingSlot()
            ->whereDate('start_at', '<=', $requestEndDate)
            ->whereDate('end_at', '>=', $requestStartDate)
            ->exists();

        unset($data['requirements'], $data['requirements_other_note']);

        $data['resources_needed'] = $requirements['summary'];
        $data['resources_details'] = json_encode($requirements['lines']);
        $data['resources_other_note'] = $requirements['other_note'];
        $data['reservation_no'] = 'FR-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        $data['user_id'] = auth()->id();
        $data['status'] = auth()->user()->canManageFacilities() ? 'approved' : 'pending';
        $data['is_pre_plotted'] = $hasPriorHold && $data['status'] === 'pending';
        $data['venue_status'] = $data['is_pre_plotted'] ? 'pending' : 'not_required';
        if ($data['status'] === 'approved') {
            $data['reviewed_by'] = auth()->id();
            $data['reviewed_at'] = now();
        }
        $reservation = FacilityReservation::create($data);

        // If it still needs review, notify FMO Super Admin / FMO staff (in-system +
        // registered email) that a new reservation is awaiting their approval.
        if ($reservation->status === 'pending') {
            foreach (\App\Models\User::query()->whereIn('role', \App\Models\User::FMO_ROLES)->get() as $manager) {
                $this->safeNotify($manager, new FacilityReservationStatusNotification(
                    $reservation->fresh(['facility', 'user']),
                    'New Facility Reservation Awaiting Approval',
                    'A new reservation for "' . $reservation->title . '" was submitted and is now waiting for your review.'
                ));
            }
        }

        $target = auth()->user()->canManageFacilities()
            ? 'fmo.reservations.index'
            : auth()->user()->homeRouteName();

        $message = 'Reservation submitted successfully.';
        if ($hasPriorHold && $reservation->status === 'pending') {
            $message .= ' This venue is already requested on the same activity date, so your slot is pre-plotted only — Facilities Management will decide which request gets it.';
        }

        return redirect()->route($target)->with('success', $message);
    }

    public function approve(FacilityReservation $reservation)
    {
        abort_unless(auth()->user()->canManageFacilities(), 403);

        if ($reservation->activityProposal) {
            return back()->withErrors([
                'reservation' => 'This reservation belongs to an Activity Proposal. Use Approve Request for the FMO approval trail, and Approve Venue separately when the request is pre-plotted.'
            ]);
        }

        if ($reservation->isPrePlotted() && !$reservation->isVenueApproved()) {
            return back()->withErrors(['reservation' => 'Approve the pre-plotted venue first before approving this standalone request.']);
        }

        if ($reservation->confirmedCompetingReservations()->exists()) {
            return back()->withErrors(['reservation' => 'This reservation conflicts with an already confirmed schedule.']);
        }

        $reservation->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'rejection_reason' => null]);

        $this->safeNotify($reservation->user, new FacilityReservationStatusNotification(
            $reservation->fresh(['facility', 'user']),
            'Facility Reservation Approved',
            'Your reservation "' . $reservation->title . '" has been approved.'
        ));

        return back()->with('success', 'Reservation approved.');
    }

    public function reject(Request $request, FacilityReservation $reservation)
    {
        abort_unless(auth()->user()->canManageFacilities(), 403);
        $data = $request->validate(['rejection_reason' => ['nullable','string','max:500']]);
        $reservation->update(['status' => 'rejected', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'rejection_reason' => $data['rejection_reason'] ?? 'Rejected by FMO']);

        $this->safeNotify($reservation->user, new FacilityReservationStatusNotification(
            $reservation->fresh(['facility', 'user']),
            'Facility Reservation Rejected',
            'Your reservation "' . $reservation->title . '" was rejected: ' . $reservation->rejection_reason
        ));

        return back()->with('success', 'Reservation rejected.');
    }

    /**
     * Only the FMO Super Admin may delete a facility reservation entirely --
     * this used to be the Asset Management Super Admin, which no longer has
     * any Facilities access at all.
     */
    public function destroyReservation(FacilityReservation $reservation)
    {
        abort_unless(auth()->user()->canDeleteFacilityRecords(), 403);

        $proposal = $reservation->activityProposal
            ?: \App\Models\ActivityProposal::where('facility_reservation_id', $reservation->id)->first();
        $attachmentPath = $proposal?->program_flow_path;

        DB::transaction(function () use ($reservation, $proposal) {
            if ($proposal) {
                $proposal->delete();
            }
            $reservation->delete();
        });

        if ($attachmentPath) {
            Storage::disk('local')->delete($attachmentPath);
        }

        return redirect()->route('fmo.reservations.index')->with('success', 'Reservation and any linked activity proposal were deleted everywhere.');
    }
}
