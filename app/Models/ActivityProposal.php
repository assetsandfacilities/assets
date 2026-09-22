<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_no', 'user_id', 'organization_name', 'requester_position', 'department_id',
        'adviser_id', 'department_approver_id', 'sdao_id', 'facilities_mgmt_id', 'academic_director_id', 'executive_director_id',
        'title', 'activity_days', 'program_flow', 'program_flow_path', 'program_flow_filename',
        'program_flow_mime', 'program_flow_extracted', 'speaker_name', 'participants_count', 'equipment_needed',
        'equipment_details', 'equipment_other_note',
        'facility_id', 'venue_other_note', 'start_at', 'end_at', 'facility_reservation_id',
        'status',
        'adviser_signed_by', 'adviser_signed_at',
        'department_signed_by', 'department_signed_at',
        'sdao_signed_by', 'sdao_signed_at',
        'fmo_signed_by', 'fmo_signed_at',
        'academic_director_signed_by', 'academic_director_signed_at',
        'executive_signed_by', 'executive_signed_at',
        'rejected_stage', 'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'adviser_signed_at' => 'datetime',
        'department_signed_at' => 'datetime',
        'sdao_signed_at' => 'datetime',
        'fmo_signed_at' => 'datetime',
        'academic_director_signed_at' => 'datetime',
        'executive_signed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function adviser() { return $this->belongsTo(User::class, 'adviser_id'); }
    public function departmentApprover() { return $this->belongsTo(User::class, 'department_approver_id'); }
    public function sdao() { return $this->belongsTo(User::class, 'sdao_id'); }
    public function facilitiesMgmt() { return $this->belongsTo(User::class, 'facilities_mgmt_id'); }
    public function academicDirector() { return $this->belongsTo(User::class, 'academic_director_id'); }
    public function executiveDirector() { return $this->belongsTo(User::class, 'executive_director_id'); }
    public function facility() { return $this->belongsTo(Facility::class); }
    public function reservation() { return $this->belongsTo(FacilityReservation::class, 'facility_reservation_id'); }

    public function adviserSigner() { return $this->belongsTo(User::class, 'adviser_signed_by'); }
    public function departmentSigner() { return $this->belongsTo(User::class, 'department_signed_by'); }
    public function sdaoSigner() { return $this->belongsTo(User::class, 'sdao_signed_by'); }
    public function fmoSigner() { return $this->belongsTo(User::class, 'fmo_signed_by'); }
    public function academicDirectorSigner() { return $this->belongsTo(User::class, 'academic_director_signed_by'); }
    public function executiveSigner() { return $this->belongsTo(User::class, 'executive_signed_by'); }
    public function rejecter() { return $this->belongsTo(User::class, 'rejected_by'); }

    /**
     * Who is allowed to open this proposal.
     *
     * Previously show() had no check at all, so ANY signed-in account could
     * read ANY proposal just by typing /activity-proposals/{id} -- including
     * Asset Management accounts that have no business seeing facility requests
     * and requestors reading other people's submissions.
     *
     * Access is stage-aware: the requestor and FMO can follow the routing,
     * while downstream approvers only gain access when their exact step is
     * reached. Approvers who already acted keep read access as history.
     */
    public function canBeViewedBy(User $user): bool
    {
        // Requestor always keeps access to their own submission.
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        // FMO Super Admin oversees the complete Facilities routing history.
        if ($user->isFmoSuperAdmin()) {
            return true;
        }

        // Assigned FMO staff may see the request from the FMO stage onward.
        if ($user->isFmoSide()) {
            return $this->facilities_mgmt_id === null
                || (int) $this->facilities_mgmt_id === (int) $user->id
                || (int) $this->fmo_signed_by === (int) $user->id;
        }

        // Downstream approvers are intentionally hidden until their stage is
        // reached. Once they have signed (or personally rejected), the proposal
        // remains visible to them as approval history.
        if ($user->isAdviserApprover() && (int) $this->adviser_id === (int) $user->id) {
            return $this->status === 'pending_adviser'
                || filled($this->adviser_signed_at)
                || (int) $this->rejected_by === (int) $user->id;
        }

        if ($user->isDeanApprover() && (int) $this->department_approver_id === (int) $user->id) {
            return $this->status === 'pending_noted'
                || filled($this->department_signed_at)
                || (int) $this->rejected_by === (int) $user->id;
        }

        if ($user->isSdaoApprover() && (int) $this->sdao_id === (int) $user->id) {
            return ($this->status === 'pending_noted' && filled($this->department_signed_at))
                || filled($this->sdao_signed_at)
                || (int) $this->rejected_by === (int) $user->id;
        }

        if ($user->isAcademicDirectorApprover() && (int) $this->academic_director_id === (int) $user->id) {
            return $this->status === 'pending_review'
                || filled($this->academic_director_signed_at)
                || (int) $this->rejected_by === (int) $user->id;
        }

        if ($user->isExecutiveApprover() && (int) $this->executive_director_id === (int) $user->id) {
            return $this->status === 'pending_executive'
                || filled($this->executive_signed_at)
                || (int) $this->rejected_by === (int) $user->id;
        }

        return false;
    }

    public function hasProgramFlowFile(): bool
    {
        return filled($this->program_flow_path);
    }

    public function equipmentList(): array
    {
        return $this->equipment_needed ? array_filter(array_map('trim', explode(',', $this->equipment_needed))) : [];
    }

    /**
     * Structured item/service breakdown with quantities. Older proposals have
     * no JSON payload, so the legacy comma-separated string is split instead
     * and rendered without quantities -- nothing is lost.
     */
    public function requirementLines(): array
    {
        return \App\Support\FacilityRequirements::decode($this->equipment_details, $this->equipment_needed);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_fmo' => 'Pending - Facilities Management Review',
            'pending_adviser' => 'Pending - Adviser / Program Chair Signature',
            'pending_noted' => $this->department_signed_at ? 'Pending - Noted By (SDAO)' : 'Pending - Noted By (Dean / Principal)',
            'pending_review' => 'Pending - Academic Director Review',
            'pending_executive' => 'Pending - Approved By (Executive Director)',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function isAwaitingFmo(): bool { return $this->status === 'pending_fmo'; }
    public function isAwaitingAdviser(): bool { return $this->status === 'pending_adviser'; }
    public function isAwaitingNoted(): bool { return $this->status === 'pending_noted'; }
    public function isAwaitingReview(): bool { return $this->status === 'pending_review'; }
    public function isAwaitingExecutive(): bool { return $this->status === 'pending_executive'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
}
