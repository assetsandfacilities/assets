<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\Facility;
use App\Models\FacilityReservation;
use App\Models\User;
use App\Notifications\ActivityProposalStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ActivityProposalController extends Controller
{
    // Matches "OTHER ITEMS NEEDED AND SERVICES" checklist on the School Facilities Reservation Form
    public const EQUIPMENT_OPTIONS = ['Table', 'Chairs', 'ITSO Services', 'Sound System', 'Flag', 'Janitors', 'Electricians', 'Others'];

    private function requireESignature(User $user): void
    {
        abort_unless($user->hasSignature(), 422, 'Your account does not have an e-signature on file. Ask the proper Super Admin to set it before approving this document.');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $filterStatus = (string) $request->string('status');
        if (!in_array($filterStatus, ['', 'pending', 'approved', 'rejected'], true)) {
            $filterStatus = '';
        }
        $from = trim((string) $request->string('from'));
        $to = trim((string) $request->string('to'));
        $departmentId = $request->integer('department') ?: null;
        $facilityId = $request->integer('facility') ?: null;
        if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }
        $query = ActivityProposal::with(['user', 'department', 'adviser', 'departmentApprover', 'sdao', 'facilitiesMgmt', 'academicDirector', 'executiveDirector', 'facility', 'reservation']);

        if ($user->isFmoSuperAdmin()) {
            // Facilities Super Admin oversees the whole routing queue.
        } elseif ($user->isAdviserApprover()) {
            // Strict step-by-step routing: the Adviser does not see the form
            // before FMO has completed the request review. After signing, keep
            // it visible as history.
            $query->where('adviser_id', $user->id)
                ->where(function ($q) use ($user) {
                    $q->where('status', 'pending_adviser')
                        ->orWhereNotNull('adviser_signed_at')
                        ->orWhere('rejected_by', $user->id);
                });
        } elseif ($user->isDeanApprover()) {
            $query->where('department_approver_id', $user->id)
                ->where(function ($q) use ($user) {
                    $q->where('status', 'pending_noted')
                        ->orWhereNotNull('department_signed_at')
                        ->orWhere('rejected_by', $user->id);
                });
        } elseif ($user->isSdaoApprover()) {
            $query->where('sdao_id', $user->id)
                ->where(function ($q) use ($user) {
                    $q->where(function ($current) {
                        $current->where('status', 'pending_noted')
                            ->whereNotNull('department_signed_at');
                    })
                        ->orWhereNotNull('sdao_signed_at')
                        ->orWhere('rejected_by', $user->id);
                });
        } elseif ($user->isFmoSide()) {
            $query->where(function ($q) use ($user) {
                $q->where('facilities_mgmt_id', $user->id)->orWhereNull('facilities_mgmt_id');
            })->whereIn('status', ['pending_fmo', 'pending_adviser', 'pending_noted', 'pending_review', 'pending_executive', 'approved', 'rejected']);
        } elseif ($user->isAcademicDirectorApprover()) {
            $query->where('academic_director_id', $user->id)
                ->where(function ($q) use ($user) {
                    $q->where('status', 'pending_review')
                        ->orWhereNotNull('academic_director_signed_at')
                        ->orWhere('rejected_by', $user->id);
                });
        } elseif ($user->isExecutiveApprover()) {
            $query->where('executive_director_id', $user->id)
                ->where(function ($q) use ($user) {
                    $q->where('status', 'pending_executive')
                        ->orWhereNotNull('executive_signed_at')
                        ->orWhere('rejected_by', $user->id);
                });
        } else {
            $query->where('user_id', $user->id);
        }

        // Search stays inside the current user's permitted proposal scope.
        // This makes the digital record easy to retrieve by requester, title,
        // proposal number, or venue without needing a printable copy.
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('proposal_no', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like))
                    ->orWhereHas('facility', fn ($facilityQuery) => $facilityQuery->where('name', 'like', $like));
            });
        }

        $query
            ->when($filterStatus === 'pending', fn ($q) => $q->whereNotIn('status', ['approved', 'rejected']))
            ->when($filterStatus === 'approved', fn ($q) => $q->where('status', 'approved'))
            ->when($filterStatus === 'rejected', fn ($q) => $q->where('status', 'rejected'))
            ->when($from !== '', fn ($q) => $q->whereDate('start_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('start_at', '<=', $to))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId));

        $proposals = $query->latest()->paginate(10)->withQueryString();
        $departments = \App\Models\Department::orderBy('name')->get(['id', 'name']);
        $facilities = Facility::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('activity_proposals.index', compact(
            'proposals', 'filterStatus', 'from', 'to', 'departmentId', 'facilityId', 'departments', 'facilities'
        ));
    }

    public function create()
    {
        $facilities = Facility::where('is_active', true)->orderBy('name')->get();
        /*
         | Panel revision: the requestor no longer picks their own signatories.
         | The Adviser / Program Chair and the Dean / Principal are whoever is
         | registered under the requestor's department; the SDAO is a single
         | campus office. The form shows them read-only for transparency.
         */
        $routingDepartment = auth()->user()->department;
        $adviser = \App\Support\SignatoryResolver::forDepartment($routingDepartment?->id, 'adviser');
        $departmentApprover = \App\Support\SignatoryResolver::forDepartment($routingDepartment?->id, 'dean');
        $sdaoOfficer = \App\Support\SignatoryResolver::resolve('sdao');

        // Academic Director and Executive Director are campus-wide roles with no
        // department, and there is exactly one of each, so the requestor is not
        // asked to pick them -- the form only shows who will sign.
        $academicDirector = \App\Support\SignatoryResolver::academicDirector();
        $executiveDirector = \App\Support\SignatoryResolver::executiveDirector();

        // The "Other Items Needed and Services" checklist is now driven by the
        // facility_items table, which the FMO Super Admin maintains.
        $catalogItems = \App\Models\FacilityItem::active()->items()->ordered()->get();
        $catalogServices = \App\Models\FacilityItem::active()->services()->ordered()->get();

        return view('activity_proposals.create', compact('facilities', 'routingDepartment', 'adviser', 'departmentApprover', 'sdaoOfficer', 'academicDirector', 'executiveDirector', 'catalogItems', 'catalogServices') + [
            'equipmentOptions' => self::EQUIPMENT_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:150'],
            'requester_position' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:150'],
            'speaker_name' => ['required', 'string', 'max:150'],
            'activity_start_at' => [
                'required',
                'date',
                // Panel revision: minimum advance notice, enforced server-side so
                // an edited date field cannot slip a same-week activity through.
                \App\Support\BookingWindow::startRule(),
            ],
            'activity_end_at' => ['required', 'date', 'after:activity_start_at'],
            'program_flow' => ['nullable', 'string'],
            'program_flow_file' => ['nullable', 'file', 'mimes:pdf,docx,txt', 'max:5120'],
            'participants_count' => ['required', 'integer', 'min:1'],
            'equipment_needed' => ['nullable', 'array'],
            'requirements' => ['nullable', 'array'],
            'requirements_other_note' => ['nullable', 'string', 'max:1000'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'venue_other_note' => ['nullable', 'string', 'max:150'],
            /*
             | Panel revision: adviser_id, department_approver_id and sdao_id are
             | no longer accepted from the form. They are resolved below from the
             | requestor's department (and the single SDAO office), so a tampered
             | POST cannot reroute a proposal to a friendlier signatory.
             */
        ], [
            // Laravel's default reads "must be a date after or equal to
            // 2026-09-20 00:00:00", which tells a student nothing useful.
            'activity_start_at.after_or_equal' => \App\Support\BookingWindow::violationMessage(),
        ]);

        /*
         | Campus-wide signatories are resolved on the server, never accepted
         | from the submitted form. NU Clark has one Academic Director and one
         | Executive Director, neither attached to a department, so there was
         | nothing meaningful for the requestor to choose -- and accepting an id
         | from the browser would have let a tampered form reroute the last two
         | approval steps to another account.
         */
        $routingDepartment = auth()->user()->department;
        $adviser = \App\Support\SignatoryResolver::forDepartment($routingDepartment?->id, 'adviser');
        $departmentApprover = \App\Support\SignatoryResolver::forDepartment($routingDepartment?->id, 'dean');
        $sdaoOfficer = \App\Support\SignatoryResolver::resolve('sdao');

        foreach ([['adviser', $adviser], ['dean', $departmentApprover]] as [$type, $signatory]) {
            if (!$signatory) {
                return back()->withInput()->withErrors([
                    'facility_id' => \App\Support\SignatoryResolver::missingDepartmentMessage($type, $routingDepartment?->name),
                ]);
            }
        }
        if (!$sdaoOfficer) {
            return back()->withInput()->withErrors([
                'facility_id' => \App\Support\SignatoryResolver::missingMessage('sdao'),
            ]);
        }

        $data['adviser_id'] = $adviser->id;
        $data['department_approver_id'] = $departmentApprover->id;
        $data['sdao_id'] = $sdaoOfficer->id;

        $academicDirector = \App\Support\SignatoryResolver::academicDirector();
        $executiveDirector = \App\Support\SignatoryResolver::executiveDirector();

        foreach ([['academic_director', $academicDirector], ['executive', $executiveDirector]] as [$type, $signatory]) {
            if (!$signatory) {
                return back()->withInput()->withErrors([
                    'facility_id' => \App\Support\SignatoryResolver::missingMessage($type),
                ]);
            }
        }

        $data['academic_director_id'] = $academicDirector->id;
        $data['executive_director_id'] = $executiveDirector->id;

        // Program Flow may be typed, uploaded, or both. When a file is given,
        // its text is extracted into program_flow so every existing screen and
        // the printed form keep working from one column, while the original
        // file stays attached for the FMO to open.
        $programFlowNote = null;
        $storedPath = $storedName = $storedMime = null;
        $extracted = false;
        $programFlow = trim((string) ($data['program_flow'] ?? ''));

        if ($request->hasFile('program_flow_file')) {
            $file = $request->file('program_flow_file');
            $storedName = $file->getClientOriginalName();
            $storedMime = $file->getClientMimeType();
            $storedPath = $file->store('program-flows');

            $result = \App\Support\DocumentTextExtractor::fromUpload($file);
            $programFlowNote = $result['note'];

            if (!empty($result['text'])) {
                // Anything the requestor typed is kept above the extracted text
                // rather than silently discarded.
                $programFlow = $programFlow !== ''
                    ? $programFlow . "\n\n--- From attached file: " . $storedName . " ---\n" . $result['text']
                    : $result['text'];
                $extracted = true;
            }
        }

        if ($programFlow === '') {
            return back()
                ->withErrors(['program_flow' => $programFlowNote
                    ? 'Program Flow is required. ' . $programFlowNote
                    : 'Please type the Program Flow or upload a readable PDF, Word (.docx) or text file.'])
                ->withInput();
        }

        $data['program_flow'] = $programFlow;
        unset($data['program_flow_file']);

        $user = auth()->user();

        /*
         | The requestor now submits two real datetimes. The previous version
         | stored only weekday names and rebuilt the dates from the current
         | week's Monday, which silently resolved a forward booking onto a date
         | that had already passed (requesting "Monday" on Saturday 9/12 landed
         | on 9/07). Parse what was actually chosen instead of reconstructing it.
         */
        $startAt = Carbon::parse($data['activity_start_at'], 'Asia/Manila');
        $endAt = Carbon::parse($data['activity_end_at'], 'Asia/Manila');

        if ($endAt->lte($startAt)) {
            throw ValidationException::withMessages([
                'activity_end_at' => 'The end of the activity must be later than the start.',
            ]);
        }

        // The weekday label on the printed proposal is derived from the stored
        // dates, so the two can no longer disagree.
        $data['activity_days'] = $startAt->isSameDay($endAt)
            ? $startAt->format('l, F j, Y')
            : $startAt->format('l, F j, Y') . ' - ' . $endAt->format('l, F j, Y');
        $data['start_at'] = $startAt;
        $data['end_at'] = $endAt;
        unset($data['activity_start_at'], $data['activity_end_at']);

        // Checkbox + quantity picker ("5 Tables, 100 Chairs, 2 ITSO Services")
        // plus the free-text "Others" note.
        $requirements = \App\Support\FacilityRequirements::fromRequest($request);
        unset($data['requirements'], $data['requirements_other_note']);

        // Capture the conflict at submission time. Pre-plotting is DATE based,
        // not time based: if an earlier active request already uses the same
        // venue on any calendar date covered by this request, this later request
        // is pre-plotted even when the clock times are different.
        $requestStartDate = $data['start_at']->toDateString();
        $requestEndDate = $data['end_at']->toDateString();
        $hasConflict = FacilityReservation::where('facility_id', $data['facility_id'])
            ->whereIn('status', ['pending', 'approved'])
            // An emergency-cancelled booking has released its date, so it no
            // longer makes a later request pre-plotted against it.
            ->holdingSlot()
            ->whereDate('start_at', '<=', $requestEndDate)
            ->whereDate('end_at', '>=', $requestStartDate)
            ->exists();

        // Keep the reservation and proposal as one atomic database unit. If
        // either insert fails, neither side is left behind as a ghost/orphan
        // record in the other account's list.
        try {
            [$reservation, $proposal] = DB::transaction(function () use ($data, $user, $requirements, $hasConflict, $storedPath, $storedName, $storedMime, $extracted) {
                $reservation = FacilityReservation::create([
                    'reservation_no' => 'FR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                    'user_id' => $user->id,
                    'facility_id' => $data['facility_id'],
                    'title' => $data['title'],
                    'purpose' => 'Activity Proposal: ' . $data['title'],
                    'resources_needed' => $requirements['summary'],
                    'resources_details' => json_encode($requirements['lines']),
                    'resources_other_note' => $requirements['other_note'],
                    'start_at' => $data['start_at'],
                    'end_at' => $data['end_at'],
                    'status' => 'pending',
                    'is_pre_plotted' => $hasConflict,
                    'venue_status' => $hasConflict ? 'pending' : 'not_required',
                ]);

                $proposal = ActivityProposal::create(array_merge($data, [
                    'proposal_no' => 'AP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                    'user_id' => $user->id,
                    'department_id' => $user->department_id,
                    'equipment_needed' => $requirements['summary'],
                    'program_flow_path' => $storedPath,
                    'program_flow_filename' => $storedName,
                    'program_flow_mime' => $storedMime,
                    'program_flow_extracted' => $extracted,
                    'equipment_details' => json_encode($requirements['lines']),
                    'equipment_other_note' => $requirements['other_note'],
                    'facility_reservation_id' => $reservation->id,
                    'status' => 'pending_fmo',
                ]));

                $reservation->update(['activity_proposal_id' => $proposal->id]);

                return [$reservation, $proposal];
            });
        } catch (\Throwable $e) {
            // A file can be stored before the DB transaction starts. Do not
            // leave that private upload orphaned when proposal creation fails.
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $e;
        }

        // Facilities Management is auto-assigned when the first authorized FMO
        // reviewer actually acts. Until then, notify all active FMO approval
        // accounts so the request appears in the shared first-review queue.
        User::query()
            ->whereIn('role', User::FMO_ROLES)
            ->where('is_approved', true)
            ->get()
            ->each(function (User $fmoUser) use ($proposal, $user) {
                $this->safeNotify($fmoUser, new ActivityProposalStatusNotification(
                    $proposal, 'New Activity Proposal Awaiting Facilities Review',
                    $user->name . ' submitted "' . $proposal->title . '". Facilities Management must review it first before the other approvers.'
                ));
            });

        $message = 'Proposal submitted and routed digitally: Facilities Management first -> Adviser / Program Chair -> Noted By (Dean/Principal and SDAO) -> Academic Director -> Executive Director.';
        if ($hasConflict) {
            // Only a contested slot is genuinely "pre-plotted" -- the FMO still
            // has to choose between the same-venue, same-date requests.
            $message .= ' Your venue slot is pre-plotted only: another request already has this venue held or approved on the same activity date, so Facilities Management will decide which one gets it.';
        } else {
            $message .= ' Your venue slot is held while it is routed.';
        }

        return redirect()->route('activity-proposals.show', $proposal)->with('success', $message);
    }

    public function show(ActivityProposal $activityProposal)
    {
        // Direct-URL protection: hiding a link is not access control.
        abort_unless($activityProposal->canBeViewedBy(auth()->user()), 403,
            'You are not part of the routing for this activity proposal.');

        $activityProposal->load(['user', 'department', 'adviser', 'departmentApprover', 'sdao', 'facilitiesMgmt', 'academicDirector', 'executiveDirector', 'facility', 'reservation', 'adviserSigner', 'departmentSigner', 'sdaoSigner', 'fmoSigner', 'academicDirectorSigner', 'executiveSigner', 'rejecter']);
        return view('activity_proposals.show', ['proposal' => $activityProposal]);
    }

    /**
     * Serves the uploaded program flow file.
     *
     * The file lives on the private disk, so it is only reachable through this
     * action -- which applies the same stakeholder rule as show(). Nothing is
     * guessable by URL and no `storage:link` is needed.
     */
    public function programFlowFile(ActivityProposal $activityProposal)
    {
        abort_unless($activityProposal->canBeViewedBy(auth()->user()), 403,
            'You are not part of the routing for this activity proposal.');
        abort_unless($activityProposal->hasProgramFlowFile(), 404, 'No program flow file is attached to this proposal.');

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($activityProposal->program_flow_path), 404, 'The attached file is no longer on the server.');

        // Inline so PDFs open in the browser tab instead of forcing a download.
        return response()->file($disk->path($activityProposal->program_flow_path), [
            'Content-Type' => $activityProposal->program_flow_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($activityProposal->program_flow_filename ?: 'program-flow') . '"',
        ]);
    }

    public function approveAdviser(ActivityProposal $activityProposal)
    {
        $p = $activityProposal;
        $user = auth()->user();
        $this->requireESignature($user);
        abort_unless($p->isAwaitingAdviser() && ($user->isFmoSuperAdmin() || $user->id === $p->adviser_id), 403);

        $p->update(['adviser_signed_by' => $user->id, 'adviser_signed_at' => now(), 'status' => 'pending_noted']);
        $this->safeNotify($p->departmentApprover, new ActivityProposalStatusNotification(
            $p,
            'Activity Proposal Awaiting Dean / Principal Signature',
            'The Adviser / Program Chair signed "' . $p->title . '". It now needs your Dean / Principal signature.'
        ));

        return back()->with('success', 'Signed as Adviser/Program Chair. Forwarded to the Dean / Principal.');
    }

    public function signDean(ActivityProposal $activityProposal)
    {
        $p = $activityProposal;
        $user = auth()->user();
        $this->requireESignature($user);
        abort_unless($p->isAwaitingNoted() && !$p->department_signed_at, 403, 'This proposal is not currently awaiting the Dean / Principal signature.');
        abort_unless($user->isFmoSuperAdmin() || $user->id === $p->department_approver_id, 403, 'You are not the Dean/Principal assigned to this proposal.');
        $p->update(['department_signed_by' => $user->id, 'department_signed_at' => now()]);
        $this->safeNotify($p->sdao, new ActivityProposalStatusNotification(
            $p,
            'Activity Proposal Awaiting SDAO Signature',
            'The Dean / Principal signed "' . $p->title . '". It now needs your SDAO signature.'
        ));
        return back()->with('success', 'Signed as Dean/Principal. Forwarded to SDAO.');
    }

    public function signSdao(ActivityProposal $activityProposal)
    {
        $p = $activityProposal;
        $user = auth()->user();
        $this->requireESignature($user);
        abort_unless($p->isAwaitingNoted() && $p->department_signed_at && !$p->sdao_signed_at, 403, 'This proposal is not currently awaiting the SDAO signature. The Dean / Principal must sign first.');
        abort_unless($user->isFmoSuperAdmin() || $user->id === $p->sdao_id, 403, 'You are not the SDAO officer assigned to this proposal (assigned SDAO user id: ' . ($p->sdao_id ?? 'none') . ', your user id: ' . $user->id . ').');
        $p->update(['sdao_signed_by' => $user->id, 'sdao_signed_at' => now(), 'status' => 'pending_review']);
        $this->safeNotify($p->academicDirector, new ActivityProposalStatusNotification(
            $p,
            'Activity Proposal Awaiting Academic Director Review',
            'The SDAO signed "' . $p->title . '". It now needs your Academic Director review.'
        ));
        return back()->with('success', 'Signed as SDAO. Forwarded to the Academic Director.');
    }

    public function signFacilities(ActivityProposal $activityProposal)
    {
        $p = $activityProposal;
        $user = auth()->user();
        $this->requireESignature($user);
        $isLegacyReview = $p->isAwaitingReview() && !$p->fmo_signed_at;
        abort_unless($p->isAwaitingFmo() || $isLegacyReview, 403, 'This proposal is not currently awaiting Facilities Management approval.');

        $isUnassignedFmoQueue = $p->facilities_mgmt_id === null && $user->isFmoSide();
        abort_unless(
            $user->isFmoSuperAdmin() || $isUnassignedFmoQueue || (int) $user->id === (int) $p->facilities_mgmt_id,
            403,
            'You are not the Facilities Management officer assigned to this proposal.'
        );

        // The requestor no longer chooses an FMO officer. The first authorized
        // FMO reviewer who performs the First Review becomes the assigned
        // Facilities Management reviewer for this proposal.
        if ($p->facilities_mgmt_id === null) {
            $p->update(['facilities_mgmt_id' => $user->id]);
            $p->refresh();
        }

        if ($p->isAwaitingFmo() && $p->fmo_signed_at) {
            return back()->withErrors(['proposal' => 'The FMO request review is already approved. This request is only waiting for its pre-plotted venue decision.']);
        }

        $reservation = $p->reservation;

        // Strict pre-plotted order: Venue Slot Approved is the first gate. The
        // FMO request review becomes actionable only after the venue decision.
        // Older records that already have fmo_signed_at are left intact and can
        // finish by approving the venue.
        if ($p->isAwaitingFmo() && $reservation?->isPrePlotted() && !$reservation->isVenueApproved()) {
            return back()->withErrors([
                'reservation' => 'Approve Venue first. The FMO Approve Request step opens only after Venue Slot Approved.'
            ]);
        }

        // A normal first request cannot be advanced if FMO has already awarded
        // this venue/date to another pre-plotted request. Likewise, a
        // pre-plotted request cannot advance against a fully confirmed booking.
        if ($p->isAwaitingFmo() && $reservation && $reservation->confirmedCompetingReservations()->exists()) {
            return back()->withErrors([
                'reservation' => 'This venue/date is already confirmed for another request. Reject or resolve that conflicting request before approving this request.'
            ]);
        }

        $updates = ['fmo_signed_by' => $user->id, 'fmo_signed_at' => now()];

        if ($p->isAwaitingFmo()) {
            // Normal first request: FMO approval immediately opens the Adviser
            // stage. Pre-plotted request: Approve Request and Approve Venue are
            // separate actions; both may be clicked in either order, but the
            // proposal only advances after both are complete.
            if (!$reservation || !$reservation->isPrePlotted() || $reservation->isVenueApproved()) {
                $updates['status'] = 'pending_adviser';
            }
        }

        $p->update($updates);
        $p->refresh();

        if ($p->status === 'pending_fmo' && $p->fmo_signed_at && $reservation?->isPrePlotted() && !$reservation->isVenueApproved()) {
            return back()->with('success', 'Request approved by FMO. Because this is pre-plotted, the approval trail is still waiting for Approve Venue before it can proceed to the Adviser / Program Chair.');
        }

        if ($p->status === 'pending_adviser') {
            $this->safeNotify($p->adviser, new ActivityProposalStatusNotification($p, 'Activity Proposal Awaiting Adviser Signature', 'Facilities Management approved "' . $p->title . '". It now needs your Adviser / Program Chair signature.'));
            return back()->with('success', 'FMO request approval completed. The proposal is now routed to the Adviser / Program Chair.');
        }

        if ($p->academic_director_signed_at) {
            $p->update(['status' => 'pending_executive']);
            $this->safeNotify($p->executiveDirector, new ActivityProposalStatusNotification($p, 'Activity Proposal Awaiting Executive Director Approval', 'Required reviews for "' . $p->title . '" are complete. It now needs final approval.'));
        }

        return back()->with('success', 'Signed as Facilities Management.');
    }

    public function signAcademicDirector(ActivityProposal $activityProposal)
    {
        $p = $activityProposal;
        $user = auth()->user();
        $this->requireESignature($user);
        abort_unless($p->isAwaitingReview(), 403, 'This proposal is not currently awaiting Academic Director review (current status: ' . $p->status . ').');
        abort_unless($user->isFmoSuperAdmin() || $user->id === $p->academic_director_id, 403, 'You are not the Academic Director assigned to this proposal.');
        $p->update(['academic_director_signed_by' => $user->id, 'academic_director_signed_at' => now()]);
        $p->refresh();
        if ($p->fmo_signed_at) {
            $p->update(['status' => 'pending_executive']);
            $this->safeNotify($p->executiveDirector, new ActivityProposalStatusNotification($p, 'Activity Proposal Awaiting Executive Director Approval', 'Facilities Management and Academic Director reviews for "' . $p->title . '" are complete. It now needs final approval.'));
        }
        return back()->with('success', 'Signed as Academic Director.');
    }

    public function approveExecutive(ActivityProposal $activityProposal)
    {
        $p = $activityProposal;
        $user = auth()->user();
        $this->requireESignature($user);
        abort_unless($p->isAwaitingExecutive(), 403, 'This proposal is not currently awaiting Executive Director approval (current status: ' . $p->status . ').');
        abort_unless($user->isFmoSuperAdmin() || $user->id === $p->executive_director_id, 403, 'You are not the Executive Director assigned to this proposal.');

        $reservation = $p->reservation;
        if ($reservation) {
            if ($reservation->isPrePlotted() && !$reservation->isVenueApproved()) {
                return back()->withErrors([
                    'reservation' => 'This request is pre-plotted and its venue has not been approved by FMO yet. Complete Venue Slot Approved first.'
                ]);
            }

            if ($reservation->confirmedCompetingReservations()->exists()) {
                return back()->withErrors(['reservation' => 'This venue/date is already confirmed for another activity. Coordinate an alternate room or time before approving.']);
            }

            $reservation->update([
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        }

        $p->update(['executive_signed_by' => $user->id, 'executive_signed_at' => now(), 'status' => 'approved']);
        $this->safeNotify($p->user, new ActivityProposalStatusNotification($p, 'Activity Proposal Approved', 'Your activity proposal "' . $p->title . '" is fully approved. The venue slot is now confirmed.'));

        return back()->with('success', 'Approved as Executive Director. Venue slot is now confirmed.');
    }

    public function reject(Request $request, ActivityProposal $activityProposal)
    {
        $p = $activityProposal;
        $user = auth()->user();
        $canReject = $user->isFmoSuperAdmin()
            || ($p->isAwaitingFmo() && $user->isFmoSide() && ($p->facilities_mgmt_id === null || (int) $user->id === (int) $p->facilities_mgmt_id))
            || ($p->isAwaitingAdviser() && $user->id === $p->adviser_id)
            || ($p->isAwaitingNoted() && (
                (!$p->department_signed_at && $user->id === $p->department_approver_id)
                || ($p->department_signed_at && !$p->sdao_signed_at && $user->id === $p->sdao_id)
            ))
            || ($p->isAwaitingReview() && (((!$p->fmo_signed_at) && (int) $user->id === (int) $p->facilities_mgmt_id) || (int) $user->id === (int) $p->academic_director_id))
            || ($p->isAwaitingExecutive() && $user->id === $p->executive_director_id);
        abort_unless($canReject, 403);

        if ($p->isAwaitingFmo() && $p->facilities_mgmt_id === null && $user->isFmoSide()) {
            $p->update(['facilities_mgmt_id' => $user->id]);
        }

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        $p->update([
            'status' => 'rejected', 'rejected_stage' => $p->status, 'rejected_by' => $user->id,
            'rejected_at' => now(), 'rejection_reason' => $data['rejection_reason'],
        ]);

        if ($p->reservation) {
            $p->reservation->update(['status' => 'rejected', 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'rejection_reason' => $data['rejection_reason']]);
        }

        $this->safeNotify($p->user, new ActivityProposalStatusNotification($p, 'Activity Proposal Rejected', 'Your activity proposal "' . $p->title . '" was rejected: ' . $data['rejection_reason']));

        return back()->with('success', 'Proposal rejected and requester notified.');
    }

    /**
     * Activity Proposals are Facilities records, so deletion now belongs to the
     * FMO Super Admin -- the Asset Management Super Admin no longer has any
     * Facilities privileges at all.
     */
    public function destroy(ActivityProposal $activityProposal)
    {
        abort_unless(auth()->user()->canDeleteFacilityRecords(), 403);

        $reservation = $activityProposal->reservation
            ?: FacilityReservation::where('activity_proposal_id', $activityProposal->id)->first();
        $attachmentPath = $activityProposal->program_flow_path;

        DB::transaction(function () use ($activityProposal, $reservation) {
            // Delete the proposal first so the circular FK on the reservation is
            // nulled, then remove the generated reservation as part of the same
            // transaction. No ghost row is left on any requestor account.
            $activityProposal->delete();
            if ($reservation) {
                $reservation->delete();
            }
        });

        if ($attachmentPath) {
            Storage::disk('local')->delete($attachmentPath);
        }

        return redirect()->route('activity-proposals.index')->with('success', 'Activity proposal and its linked reservation were deleted everywhere.');
    }

    /**
     * All direct ->notify() calls in this controller (adviser on create, executive
     * approval, rejection) go through this helper instead, so a broken mail/SMTP setup
     * never turns a successful signature/approval into a raw 500 error for the user.
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
}
