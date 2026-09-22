<?php

namespace App\Models;

use App\Support\FacilityRequirements;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacilityReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_no','user_id','facility_id','title','purpose','resources_needed',
        'resources_details','resources_other_note',
        'start_at','end_at','status','is_pre_plotted','venue_status',
        'venue_reviewed_by','venue_reviewed_at','venue_rejection_reason',
        'reviewed_by','reviewed_at','rejection_reason','activity_proposal_id',
        // Emergency cancellation and rebooking (panel feature).
        'emergency_cancelled_at','emergency_cancelled_by','emergency_reason','emergency_options',
        'rebooking_status','proposed_kind','proposed_start_at','proposed_end_at','proposed_facility_id',
        'proposed_at','rebooking_decision_note','rebooking_decided_at','rebooking_decided_by',
        'original_start_at','original_end_at','original_facility_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'venue_reviewed_at' => 'datetime',
        'is_pre_plotted' => 'boolean',
        'emergency_cancelled_at' => 'datetime',
        'proposed_start_at' => 'datetime',
        'proposed_end_at' => 'datetime',
        'proposed_at' => 'datetime',
        'rebooking_decided_at' => 'datetime',
        'original_start_at' => 'datetime',
        'original_end_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function facility() { return $this->belongsTo(Facility::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function venueReviewer() { return $this->belongsTo(User::class, 'venue_reviewed_by'); }
    public function activityProposal() { return $this->belongsTo(ActivityProposal::class); }
    public function emergencyCanceller() { return $this->belongsTo(User::class, 'emergency_cancelled_by'); }
    public function proposedFacility() { return $this->belongsTo(Facility::class, 'proposed_facility_id'); }
    public function originalFacility() { return $this->belongsTo(Facility::class, 'original_facility_id'); }
    public function rebookingDecider() { return $this->belongsTo(User::class, 'rebooking_decided_by'); }


    /**
     * Limits a query to bookings that actually occupy their slot. An
     * emergency-cancelled booking is excluded while its rebooking is open,
     * because its date has been released back to the Facilities Office.
     */
    public function scopeHoldingSlot($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('rebooking_status')
                ->orWhere('rebooking_status', self::REBOOK_DONE);
        });
    }

    /** Memoised so list pages do not re-run the same overlap query per render. */
    private ?int $competingCountCache = null;
    private ?int $priorCountCache = null;

    /**
     * Other live reservations for the same venue that share at least one
     * CALENDAR DATE with this request. Time is intentionally ignored for
     * pre-plotting: once an earlier request exists for the same venue/date, a
     * later request is pre-plotted even if the clock times are different.
     * Rejected requests no longer hold or compete for a date.
     */
    public function competingReservations()
    {
        $startDate = optional($this->start_at)->toDateString();
        $endDate = optional($this->end_at)->toDateString();

        return static::query()
            ->where('facility_id', $this->facility_id)
            ->where('id', '!=', $this->id)
            ->whereIn('status', ['pending', 'approved'])
            ->holdingSlot()
            ->whereDate('start_at', '<=', $endDate)
            ->whereDate('end_at', '>=', $startDate);
    }

    public function competingCount(): int
    {
        if ($this->competingCountCache === null) {
            $this->competingCountCache = $this->competingReservations()->count();
        }

        return $this->competingCountCache;
    }

    /**
     * Requests that were submitted before this one and share the same venue/date.
     * Submission order, not later approval order, determines which request was
     * originally first and which request was pre-plotted.
     */
    public function priorReservations()
    {
        return $this->competingReservations()
            ->where(function ($q) {
                if ($this->created_at) {
                    $q->where('created_at', '<', $this->created_at)
                        ->orWhere(function ($tie) {
                            $tie->where('created_at', $this->created_at)
                                ->where('id', '<', $this->id);
                        });
                } else {
                    $q->where('id', '<', $this->id);
                }
            });
    }

    public function priorCount(): int
    {
        if ($this->priorCountCache === null) {
            $this->priorCountCache = $this->priorReservations()->count();
        }

        return $this->priorCountCache;
    }

    /**
     * A confirmed competing venue/date is either a fully approved reservation or a
     * pre-plotted reservation whose venue was explicitly approved by FMO even
     * though its proposal may still be travelling through the signature chain.
     */
    public function confirmedCompetingReservations()
    {
        return $this->competingReservations()->where(function ($q) {
            $q->where('status', 'approved')
                ->orWhere(function ($prePlotted) {
                    $prePlotted->where('is_pre_plotted', true)
                        ->where('venue_status', 'approved');
                });
        });
    }

    /**
     * A request becomes committed once its venue is confirmed OR FMO has
     * already approved its Activity Proposal request. A later pre-plotted
     * request must not silently take that date; FMO must reject/re-resolve the
     * earlier committed request first.
     */
    public function committedCompetingReservations()
    {
        return $this->competingReservations()->where(function ($q) {
            $q->where('status', 'approved')
                ->orWhere(function ($prePlotted) {
                    $prePlotted->where('is_pre_plotted', true)
                        ->where('venue_status', 'approved');
                })
                ->orWhereHas('activityProposal', function ($proposal) {
                    $proposal->whereNotNull('fmo_signed_at')
                        ->where('status', '!=', 'rejected');
                });
        });
    }

    /**
     * Pre-plotted is stored at submission time. This is intentionally not
     * recalculated later: the first request stays a normal Pending request even
     * after a second user submits a same-venue, same-date reservation.
     */
    public function isPrePlotted(): bool
    {
        if (array_key_exists('is_pre_plotted', $this->attributes)) {
            return (bool) $this->is_pre_plotted;
        }

        // Backward-compatible fallback for a database that has not migrated yet.
        return $this->status === 'pending' && $this->priorCount() > 0;
    }

    public function isVenuePending(): bool
    {
        return $this->isPrePlotted() && ($this->venue_status ?? 'pending') === 'pending';
    }

    public function isVenueApproved(): bool
    {
        return $this->isPrePlotted() && $this->venue_status === 'approved';
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    /* ----------------------------------------------------------------------
     | Emergency cancellation and rebooking
     |
     | The booking keeps its status and its signed approval trail throughout.
     | What changes is the SLOT: while the rebooking conversation is open the
     | reservation holds no date, which is what frees the venue for the office.
     * -------------------------------------------------------------------- */

    public const REBOOK_AWAITING_REQUESTOR = 'awaiting_requestor';
    public const REBOOK_AWAITING_FMO = 'awaiting_fmo';
    public const REBOOK_DONE = 'rebooked';

    /** Every approver has signed, so only the venue decision remains ours. */
    public function isFullyApproved(): bool
    {
        $proposal = $this->activityProposal;

        return $proposal
            ? $proposal->status === 'approved'
            : $this->status === 'approved';
    }

    public function isEmergencyCancelled(): bool
    {
        return $this->emergency_cancelled_at !== null;
    }

    /**
     * Whether the office may release this booking right now.
     *
     * A booking that already moved once can hit a second emergency, so a
     * completed rebooking does not lock the action out -- only a cancellation
     * that is still being resolved does. The controller and the button on the
     * detail page both ask this, so what is offered and what is allowed cannot
     * drift apart.
     */
    public function canBeEmergencyCancelled(): bool
    {
        // Facilities may need to release a venue before the entire approval
        // route has finished. Keep every approval already recorded, but do not
        // offer emergency cancellation for a rejected request or while another
        // emergency rebooking is still unresolved.
        if ($this->isRejected()) {
            return false;
        }

        return !$this->isEmergencyCancelled() || $this->wasRebooked();
    }

    /** Waiting for the requestor to choose a new date or venue. */
    public function awaitingRequestorChoice(): bool
    {
        return $this->rebooking_status === self::REBOOK_AWAITING_REQUESTOR;
    }

    /** The requestor has chosen; the office must decide on the new slot. */
    public function awaitingFmoRebooking(): bool
    {
        return $this->rebooking_status === self::REBOOK_AWAITING_FMO;
    }

    public function wasRebooked(): bool
    {
        return $this->rebooking_status === self::REBOOK_DONE;
    }

    /**
     * True while this booking occupies no date at all. Used by the overlap
     * queries so an emergency-cancelled activity stops blocking its venue the
     * moment the office cancels it -- that is the point of the feature.
     */
    public function holdsNoSlot(): bool
    {
        return in_array($this->rebooking_status, [self::REBOOK_AWAITING_REQUESTOR, self::REBOOK_AWAITING_FMO], true);
    }

    /** What the requestor is allowed to change: 'both', 'date' or 'venue'. */
    public function mayChangeDate(): bool
    {
        return in_array($this->emergency_options, ['both', 'date'], true);
    }

    public function mayChangeVenue(): bool
    {
        return in_array($this->emergency_options, ['both', 'venue'], true);
    }

    public function displayStatus(): string
    {
        if ($this->isRejected()) {
            return 'Rejected';
        }
        if ($this->awaitingRequestorChoice()) {
            return 'Cancelled — awaiting requestor';
        }
        if ($this->awaitingFmoRebooking()) {
            return 'Rebooking — awaiting FMO';
        }
        if ($this->isPrePlotted() && $this->isVenueApproved() && !$this->isApproved()) {
            return 'Pre-Plotted — Venue Approved';
        }
        if ($this->isPrePlotted() && !$this->isApproved()) {
            return 'Pre-Plotted';
        }

        return ucfirst($this->status);
    }

    /** Structured "Other Items Needed and Services" breakdown with quantities. */
    public function requirementLines(): array
    {
        $proposal = $this->activityProposal;
        if ($proposal) {
            return FacilityRequirements::decode($proposal->equipment_details, $proposal->equipment_needed);
        }

        return FacilityRequirements::decode($this->resources_details, $this->resources_needed);
    }

    public function requirementOtherNote(): ?string
    {
        $proposal = $this->activityProposal;
        if ($proposal && $proposal->equipment_other_note) {
            return $proposal->equipment_other_note;
        }

        return $this->resources_other_note;
    }

    private function venueTrailStep(): array
    {
        $venueStatus = $this->venue_status ?? 'pending';

        $state = match ($venueStatus) {
            'approved' => 'signed',
            'rejected' => 'rejected',
            default => $this->status === 'rejected' ? 'blocked' : 'waiting',
        };

        return [
            'role' => 'Venue Slot Approved',
            'name' => $this->venueReviewer->display_name ?? 'Awaiting FMO venue decision',
            'state' => $state,
            'at' => $this->venue_reviewed_at,
            'note' => $this->venue_rejection_reason,
        ];
    }

    /**
     * Full approval trail used on the FMO screen.
     *
     * Normal first requests do NOT contain a Venue Slot Approved
     * step. Only requests that were pre-plotted at submission receive that step,
     * and for them it appears at the very top of the trail.
     */
    public function approvalTrail(): array
    {
        $steps = [];

        if ($this->isPrePlotted()) {
            $steps[] = $this->venueTrailStep();
        }

        $steps[] = [
            'role' => 'Submitted By — Requestor',
            'name' => $this->user->name ?? 'N/A',
            'state' => 'signed',
            'at' => $this->created_at,
            'note' => $this->user->department->name ?? null,
        ];

        $proposal = $this->activityProposal;

        if ($proposal) {
            $chain = [
                ['First Review — Facilities Management (FMO)', $proposal->fmoSigner ?? $proposal->facilitiesMgmt, $proposal->fmo_signed_at, 'pending_fmo'],
                ['Prepared By — Adviser / Program Chair', $proposal->adviserSigner ?? $proposal->adviser, $proposal->adviser_signed_at, 'pending_adviser'],
                ['Noted By — Dean / Principal', $proposal->departmentSigner ?? $proposal->departmentApprover, $proposal->department_signed_at, 'pending_noted'],
                ['Noted By — SDAO', $proposal->sdaoSigner ?? $proposal->sdao, $proposal->sdao_signed_at, 'pending_noted'],
                ['Reviewed By — Academic Director', $proposal->academicDirectorSigner ?? $proposal->academicDirector, $proposal->academic_director_signed_at, 'pending_review'],
                ['Approved By — Executive Director', $proposal->executiveSigner ?? $proposal->executiveDirector, $proposal->executive_signed_at, 'pending_executive'],
            ];

            foreach ($chain as [$label, $person, $signedAt, $stage]) {
                if ($signedAt) {
                    $state = 'signed';
                } elseif ($proposal->status === 'rejected') {
                    $state = 'blocked';
                } elseif ($stage === 'pending_fmo' && $proposal->status === 'pending_fmo') {
                    // For pre-plotted requests, the venue decision is step 1;
                    // the FMO request review is step 2 and waits its turn.
                    $state = $this->isPrePlotted() && !$this->isVenueApproved() ? 'pending' : 'waiting';
                } elseif ($stage === 'pending_noted' && $proposal->status === 'pending_noted') {
                    if (str_contains($label, 'Dean / Principal')) {
                        $state = !$proposal->department_signed_at ? 'waiting' : 'signed';
                    } elseif (str_contains($label, 'SDAO')) {
                        $state = $proposal->department_signed_at && !$proposal->sdao_signed_at ? 'waiting' : 'pending';
                    } else {
                        $state = 'pending';
                    }
                } elseif ($proposal->status === $stage) {
                    $state = 'waiting';
                } else {
                    $state = 'pending';
                }

                $steps[] = [
                    'role' => $label,
                    'name' => $person->display_name ?? 'Not yet assigned',
                    'state' => $state,
                    'at' => $signedAt,
                    'note' => null,
                ];
            }

            if ($proposal->status === 'rejected') {
                $steps[] = [
                    'role' => 'Rejected',
                    'name' => $proposal->rejecter->display_name ?? 'N/A',
                    'state' => 'rejected',
                    'at' => $proposal->rejected_at,
                    'note' => $proposal->rejection_reason,
                ];
            }
        } else {
            // Standalone reservation: the FMO review itself is the request
            // approval step. A pre-plotted standalone reservation still has the
            // separate venue decision above it.
            $steps[] = [
                'role' => 'First Review — Facilities Management (FMO)',
                'name' => $this->reviewer->display_name ?? 'FMO Officer',
                'state' => match ($this->status) {
                    'approved' => 'signed',
                    'rejected' => 'rejected',
                    default => 'waiting',
                },
                'at' => $this->reviewed_at,
                'note' => $this->rejection_reason,
            ];
        }

        return $steps;
    }

    /** "4 of 8 approved" style counter used on the reservation list. */
    public function approvalProgress(): array
    {
        $trail = $this->approvalTrail();
        $done = count(array_filter($trail, fn ($step) => $step['state'] === 'signed'));

        return ['done' => $done, 'total' => count($trail)];
    }

    public function approvedByNames(): array
    {
        return array_values(array_map(
            fn ($step) => $step['name'],
            array_filter($this->approvalTrail(), fn ($step) => $step['state'] === 'signed')
        ));
    }

    public function pendingApproverNames(): array
    {
        return array_values(array_map(
            fn ($step) => $step['name'] . ' (' . $step['role'] . ')',
            array_filter($this->approvalTrail(), fn ($step) => in_array($step['state'], ['waiting', 'pending'], true))
        ));
    }
}
