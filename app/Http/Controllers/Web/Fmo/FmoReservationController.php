<?php

namespace App\Http\Controllers\Web\Fmo;

use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\FacilityReservation;
use App\Models\Department;
use App\Models\Facility;
use App\Notifications\ActivityProposalStatusNotification;
use App\Notifications\FacilityReservationStatusNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Reservation Requests screen for the FMO account: status filter, search, and
 * a full "View All Details" page that shows everything the requestor filled up
 * together with the complete approver trail.
 */
class FmoReservationController extends Controller
{
    private const STATUSES = ['pending', 'pre_plotted', 'approved', 'rejected'];

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

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $from = trim((string) $request->string('from'));
        $to = trim((string) $request->string('to'));
        $departmentId = $request->integer('department') ?: null;
        $facilityId = $request->integer('facility') ?: null;
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }

        // Keep search/date/department/venue filters in one reusable query so
        // the status counts always describe the records currently being viewed.
        $baseQuery = FacilityReservation::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('reservation_no', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('facility', fn ($f) => $f->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($from !== '', fn ($q) => $q->whereDate('start_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('start_at', '<=', $to))
            ->when($departmentId, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department_id', $departmentId)))
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId));

        $reservations = (clone $baseQuery)
            ->with(['facility', 'user.department', 'reviewer', 'venueReviewer', 'activityProposal'])
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending')->where('is_pre_plotted', false))
            ->when($status === 'pre_plotted', fn ($q) => $q->where('status', 'pending')->where('is_pre_plotted', true))
            ->when(in_array($status, ['approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('start_at')
            ->paginate(10)
            ->withQueryString();

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->where('is_pre_plotted', false)->count(),
            'pre_plotted' => (clone $baseQuery)->where('status', 'pending')->where('is_pre_plotted', true)->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $facilities = Facility::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('fmo.reservations.index', compact(
            'reservations', 'search', 'status', 'counts', 'from', 'to',
            'departmentId', 'facilityId', 'departments', 'facilities'
        ));
    }

    public function show(FacilityReservation $reservation): View
    {
        $reservation->load([
            'facility', 'user.department', 'reviewer', 'venueReviewer',
            'activityProposal.user', 'activityProposal.department', 'activityProposal.facility',
            'activityProposal.adviser', 'activityProposal.departmentApprover', 'activityProposal.sdao',
            'activityProposal.facilitiesMgmt', 'activityProposal.academicDirector', 'activityProposal.executiveDirector',
            'activityProposal.adviserSigner', 'activityProposal.departmentSigner', 'activityProposal.sdaoSigner',
            'activityProposal.fmoSigner', 'activityProposal.academicDirectorSigner', 'activityProposal.executiveSigner',
            'activityProposal.rejecter',
        ]);

        return view('fmo.reservations.show', [
            'reservation' => $reservation,
            'proposal' => $reservation->activityProposal,
            'trail' => $reservation->approvalTrail(),
            'requirements' => $reservation->requirementLines(),
            'otherNote' => $reservation->requirementOtherNote(),
        ]);
    }

    /**
     * Venue decision for a pre-plotted request. This is deliberately separate
     * from the proposal's FMO "Approve Request" signature.
     */
    public function approveVenue(FacilityReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->isPrePlotted(), 422, 'Only a pre-plotted reservation needs a separate venue confirmation.');
        abort_if($reservation->isRejected(), 422, 'A rejected request cannot have its venue approved.');

        if ($reservation->isVenueApproved()) {
            return back()->with('success', 'This pre-plotted venue is already approved.');
        }

        if ($reservation->committedCompetingReservations()->exists()) {
            return back()->withErrors([
                'reservation' => 'A same-date request already has this venue confirmed or has already passed FMO request approval. Reject or resolve that committed request first before assigning this pre-plotted venue.'
            ]);
        }

        $reservation->update([
            'venue_status' => 'approved',
            'venue_reviewed_by' => auth()->id(),
            'venue_reviewed_at' => now(),
            'venue_rejection_reason' => null,
        ]);

        $proposal = $reservation->activityProposal;
        if ($proposal && $proposal->status === 'pending_fmo' && $proposal->fmo_signed_at) {
            $proposal->update(['status' => 'pending_adviser']);
            $this->safeNotify($proposal->adviser, new ActivityProposalStatusNotification(
                $proposal,
                'Activity Proposal Awaiting Adviser Signature',
                'Facilities Management completed both the request review and pre-plotted venue confirmation for "' . $proposal->title . '". It now needs your Adviser / Program Chair signature.'
            ));
        }

        $this->safeNotify($reservation->user, new FacilityReservationStatusNotification(
            $reservation->fresh(['facility', 'user']),
            'Pre-Plotted Venue Approved',
            'Facilities Management approved the pre-plotted venue slot for "' . $reservation->title . '". The request will continue once all required approval steps are complete.'
        ));

        $message = $proposal && $proposal->fmo_signed_at
            ? 'Venue approved. The FMO request approval was already complete, so the proposal is now routed to the Adviser / Program Chair.'
            : 'Venue approved. Approve Request is still required before the proposal can proceed to the next approver.';

        return back()->with('success', $message);
    }

    /** Standalone reservation approval. Activity Proposals use Approve Request. */
    public function approve(FacilityReservation $reservation): RedirectResponse
    {
        if ($reservation->activityProposal) {
            return back()->withErrors([
                'reservation' => 'This reservation belongs to an Activity Proposal. Use Approve Request for the FMO approval trail instead of approving the reservation directly.'
            ]);
        }

        if ($reservation->isPrePlotted() && !$reservation->isVenueApproved()) {
            return back()->withErrors(['reservation' => 'Approve Venue first for this pre-plotted standalone reservation.']);
        }

        if ($reservation->confirmedCompetingReservations()->exists()) {
            return back()->withErrors(['reservation' => 'This reservation conflicts with an already confirmed schedule for the same venue.']);
        }

        $reservation->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->safeNotify($reservation->user, new FacilityReservationStatusNotification(
            $reservation->fresh(['facility', 'user']),
            'Facility Reservation Approved',
            'Your reservation "' . $reservation->title . '" has been approved by the Facilities Management Office.'
        ));

        return back()->with('success', 'Reservation approved.');
    }

    public function reject(Request $request, FacilityReservation $reservation): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);
        $reason = $data['rejection_reason'] ?? 'Rejected by the Facilities Management Office';
        $proposal = $reservation->activityProposal;

        if ($proposal && !in_array($proposal->status, ['approved', 'rejected'], true)) {
            $user = auth()->user();
            $canRejectProposal = $user->isFmoSuperAdmin()
                || ($proposal->isAwaitingFmo() && $user->isFmoSide() && ($proposal->facilities_mgmt_id === null || (int) $user->id === (int) $proposal->facilities_mgmt_id));
            abort_unless($canRejectProposal, 403, 'Only an available/assigned FMO reviewer or FMO Super Admin can reject this proposal at the current stage.');

            if ($proposal->isAwaitingFmo() && $proposal->facilities_mgmt_id === null && $user->isFmoSide()) {
                $proposal->update(['facilities_mgmt_id' => $user->id]);
            }

            $proposal->update([
                'status' => 'rejected',
                'rejected_stage' => $proposal->status,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
        }

        $reservation->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        if ($proposal) {
            $this->safeNotify($proposal->user, new ActivityProposalStatusNotification(
                $proposal,
                'Activity Proposal Rejected',
                'Your activity proposal "' . $proposal->title . '" was rejected: ' . $reason
            ));
        } else {
            $this->safeNotify($reservation->user, new FacilityReservationStatusNotification(
                $reservation->fresh(['facility', 'user']),
                'Facility Reservation Rejected',
                'Your reservation "' . $reservation->title . '" was rejected: ' . $reason
            ));
        }

        return back()->with('success', 'Request rejected and the requestor has been notified.');
    }

    /**
     * FMO Super Admin deletion is synchronized: the reservation and its linked
     * Activity Proposal are removed in one transaction, so no requestor account
     * can keep a ghost copy after deletion.
     */
    /* ----------------------------------------------------------------------
     | Emergency cancellation
     |
     | Used when a confirmed activity has to give up its venue at short notice,
     | typically because the administration needs the space. This is not a
     | rejection: every approver has already signed and none of those decisions
     | are touched. What the office does here is release the SLOT and open a
     | conversation with the requestor about a new date or a different venue.
     * -------------------------------------------------------------------- */

    public function emergencyCancel(Request $request, FacilityReservation $reservation): RedirectResponse
    {
        abort_unless(auth()->user()->canManageFacilities(), 403, 'Only Facilities Management can perform an emergency cancellation.');

        $data = $request->validate([
            'emergency_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'emergency_options' => ['required', 'in:both,date,venue'],
        ], [
            'emergency_reason.required' => 'Give the requestor a reason — this is the message they receive by email.',
            'emergency_reason.min' => 'Please write a fuller reason; the requestor sees exactly this text.',
        ]);

        if (!$reservation->canBeEmergencyCancelled()) {
            return back()->withErrors(['emergency_reason' => 'This booking is already under an emergency cancellation that has not been resolved yet.']);
        }

        $reservation->update([
            // The slot the activity is losing, kept so both sides can see what
            // it was moved from.
            'original_start_at' => $reservation->original_start_at ?? $reservation->start_at,
            'original_end_at' => $reservation->original_end_at ?? $reservation->end_at,
            'original_facility_id' => $reservation->original_facility_id ?? $reservation->facility_id,

            'emergency_cancelled_at' => now(),
            'emergency_cancelled_by' => auth()->id(),
            'emergency_reason' => $data['emergency_reason'],
            'emergency_options' => $data['emergency_options'],
            'rebooking_status' => FacilityReservation::REBOOK_AWAITING_REQUESTOR,

            // Any earlier proposal is cleared: this is a fresh conversation.
            'proposed_kind' => null,
            'proposed_start_at' => null,
            'proposed_end_at' => null,
            'proposed_facility_id' => null,
            'proposed_at' => null,
            'rebooking_decision_note' => null,
            'rebooking_decided_at' => null,
            'rebooking_decided_by' => null,
        ]);

        $choice = match ($data['emergency_options']) {
            'date' => 'You may choose a new date and time for the same venue.',
            'venue' => 'You may choose a different venue for the same date.',
            default => 'You may choose a new date and time, or keep the date and move to a different venue.',
        };

        $this->safeNotify($reservation->user, new \App\Notifications\ReservationEmergencyNotification(
            $reservation->fresh(['facility', 'user']),
            'Your venue booking was cancelled for an emergency',
            'The Facilities Management Office had to release the venue for your activity. Any approvals already recorded will remain in place — only the schedule is affected.',
            route('reservations.rebook.edit', $reservation),
            'Choose a new schedule',
            [
                'Reason given: '.$data['emergency_reason'],
                'Original schedule: '.optional($reservation->original_start_at ?? $reservation->start_at)->format('M d, Y h:i A')
                    .' at '.($reservation->facility->name ?? 'the assigned venue'),
                $choice,
            ]
        ));

        return back()->with('success', 'Booking cancelled for an emergency. The requestor has been notified in the system and by email, and the venue is now free for that date.');
    }

    public function approveRebooking(FacilityReservation $reservation): RedirectResponse
    {
        abort_unless(auth()->user()->canManageFacilities(), 403, 'Only Facilities Management can decide on a rebooking.');
        abort_unless($reservation->awaitingFmoRebooking(), 403, 'This booking has no rebooking waiting for a decision.');

        $newFacilityId = $reservation->proposed_facility_id ?? $reservation->facility_id;
        $newStart = $reservation->proposed_start_at ?? $reservation->start_at;
        $newEnd = $reservation->proposed_end_at ?? $reservation->end_at;

        /*
         | The new slot is checked against live bookings the same way a fresh
         | request would be. Done inside a transaction with the check immediately
         | before the write, so two rebookings racing for the same freed venue
         | cannot both be approved.
         */
        try {
            DB::transaction(function () use ($reservation, $newFacilityId, $newStart, $newEnd) {
                $clash = FacilityReservation::where('facility_id', $newFacilityId)
                    ->where('id', '!=', $reservation->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->holdingSlot()
                    ->whereDate('start_at', '<=', $newEnd->toDateString())
                    ->whereDate('end_at', '>=', $newStart->toDateString())
                    ->where(function ($q) {
                        $q->where('status', 'approved')
                            ->orWhere(function ($prePlotted) {
                                $prePlotted->where('is_pre_plotted', true)->where('venue_status', 'approved');
                            });
                    })
                    ->exists();

                if ($clash) {
                    throw new \RuntimeException('That venue and date are already held by another confirmed booking. Ask the requestor to choose again, or free the other booking first.');
                }

                $reservation->update([
                    'facility_id' => $newFacilityId,
                    'start_at' => $newStart,
                    'end_at' => $newEnd,
                    'rebooking_status' => FacilityReservation::REBOOK_DONE,
                    'rebooking_decided_at' => now(),
                    'rebooking_decided_by' => auth()->id(),
                    'rebooking_decision_note' => null,
                ]);

                // The linked Activity Proposal carries the same schedule on its
                // printed form, so it moves with the booking. Its approval
                // columns are deliberately untouched.
                if ($proposal = $reservation->activityProposal) {
                    $proposal->update([
                        'facility_id' => $newFacilityId,
                        'start_at' => $newStart,
                        'end_at' => $newEnd,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['rebooking' => $e->getMessage()]);
        }

        $fresh = $reservation->fresh(['facility', 'user']);

        $this->safeNotify($fresh->user, new \App\Notifications\ReservationEmergencyNotification(
            $fresh,
            'Your new schedule is confirmed',
            'The Facilities Management Office approved the replacement schedule you chose.',
            route('activity-proposals.index'),
            'View your booking',
            [
                'New schedule: '.optional($fresh->start_at)->format('M d, Y h:i A').' — '.optional($fresh->end_at)->format('h:i A'),
                'Venue: '.($fresh->facility->name ?? 'N/A'),
            ]
        ));

        return back()->with('success', 'Rebooking approved. The activity now sits on its new schedule and the requestor has been notified.');
    }

    public function declineRebooking(Request $request, FacilityReservation $reservation): RedirectResponse
    {
        abort_unless(auth()->user()->canManageFacilities(), 403, 'Only Facilities Management can decide on a rebooking.');
        abort_unless($reservation->awaitingFmoRebooking(), 403, 'This booking has no rebooking waiting for a decision.');

        $data = $request->validate([
            'rebooking_decision_note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rebooking_decision_note.required' => 'Tell the requestor why this schedule does not work, so their next choice is a better one.',
        ]);

        // Back to the requestor with the same options still open.
        $reservation->update([
            'rebooking_status' => FacilityReservation::REBOOK_AWAITING_REQUESTOR,
            'rebooking_decision_note' => $data['rebooking_decision_note'],
            'rebooking_decided_at' => now(),
            'rebooking_decided_by' => auth()->id(),
            'proposed_kind' => null,
            'proposed_start_at' => null,
            'proposed_end_at' => null,
            'proposed_facility_id' => null,
            'proposed_at' => null,
        ]);

        $this->safeNotify($reservation->user, new \App\Notifications\ReservationEmergencyNotification(
            $reservation->fresh(['facility', 'user']),
            'Please choose another schedule',
            'The Facilities Management Office could not confirm the schedule you picked.',
            route('reservations.rebook.edit', $reservation),
            'Choose again',
            ['Reason: '.$data['rebooking_decision_note']]
        ));

        return back()->with('success', 'Sent back to the requestor with your note.');
    }

    public function destroy(FacilityReservation $reservation): RedirectResponse
    {
        $proposal = $reservation->activityProposal
            ?: ActivityProposal::where('facility_reservation_id', $reservation->id)->first();
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
