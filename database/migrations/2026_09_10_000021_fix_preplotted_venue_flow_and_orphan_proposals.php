<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the "pre-plotted" state at submission time and separate the venue
 * decision from the activity-proposal approval trail.
 *
 * This migration also repairs the old circular proposal/reservation pointers
 * and removes true orphan records left behind by the previous one-sided delete
 * behavior. That makes already-deleted FMO proposals disappear from requestor
 * lists as well instead of surviving as ghost reservation rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('facility_reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('facility_reservations', 'is_pre_plotted')) {
                $table->boolean('is_pre_plotted')->default(false)->after('status')->index();
            }
            if (!Schema::hasColumn('facility_reservations', 'venue_status')) {
                // not_required | pending | approved | rejected
                $table->string('venue_status')->default('not_required')->after('is_pre_plotted');
            }
            if (!Schema::hasColumn('facility_reservations', 'venue_reviewed_by')) {
                $table->foreignId('venue_reviewed_by')->nullable()->after('venue_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('facility_reservations', 'venue_reviewed_at')) {
                $table->timestamp('venue_reviewed_at')->nullable()->after('venue_reviewed_by');
            }
            if (!Schema::hasColumn('facility_reservations', 'venue_rejection_reason')) {
                $table->text('venue_rejection_reason')->nullable()->after('venue_reviewed_at');
            }
        });

        // Repair one-sided links first. The old schema intentionally used
        // nullOnDelete() in both directions, so deleting only one record could
        // leave the other side behind.
        if (Schema::hasTable('activity_proposals')) {
            $proposals = DB::table('activity_proposals')
                ->whereNotNull('facility_reservation_id')
                ->select('id', 'facility_reservation_id')
                ->get();

            foreach ($proposals as $proposal) {
                DB::table('facility_reservations')
                    ->where('id', $proposal->facility_reservation_id)
                    ->whereNull('activity_proposal_id')
                    ->update(['activity_proposal_id' => $proposal->id]);
            }

            $reservations = DB::table('facility_reservations')
                ->whereNotNull('activity_proposal_id')
                ->select('id', 'activity_proposal_id')
                ->get();

            foreach ($reservations as $reservation) {
                DB::table('activity_proposals')
                    ->where('id', $reservation->activity_proposal_id)
                    ->whereNull('facility_reservation_id')
                    ->update(['facility_reservation_id' => $reservation->id]);
            }

            // Old FMO delete bug: an Activity Proposal could be removed while
            // its generated reservation remained. Only remove rows that are
            // unambiguously generated from an Activity Proposal and have no
            // surviving proposal pointing back to them.
            DB::table('facility_reservations')
                ->whereNull('activity_proposal_id')
                ->where('purpose', 'like', 'Activity Proposal:%')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('activity_proposals')
                        ->whereColumn('activity_proposals.facility_reservation_id', 'facility_reservations.id');
                })
                ->delete();

            // Symmetric cleanup in case a reservation was deleted from the FMO
            // queue while its Activity Proposal survived on a requestor account.
            DB::table('activity_proposals')
                ->whereNull('facility_reservation_id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('facility_reservations')
                        ->whereColumn('facility_reservations.activity_proposal_id', 'activity_proposals.id');
                })
                ->delete();
        }

        // Backfill existing reservations using submission order. The request
        // that arrived first remains a normal Pending request; only later
        // same-date requests are marked pre-plotted, regardless of time.
        $rows = DB::table('facility_reservations')
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('id')
            ->get(['id', 'facility_id', 'start_at', 'end_at', 'status']);

        foreach ($rows as $row) {
            $rowStartDate = Carbon::parse($row->start_at)->toDateString();
            $rowEndDate = Carbon::parse($row->end_at)->toDateString();

            $hasEarlierOverlap = DB::table('facility_reservations')
                ->where('facility_id', $row->facility_id)
                ->where('id', '<', $row->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_at', '<=', $rowEndDate)
                ->whereDate('end_at', '>=', $rowStartDate)
                ->exists();

            DB::table('facility_reservations')->where('id', $row->id)->update([
                'is_pre_plotted' => $hasEarlierOverlap,
                'venue_status' => $hasEarlierOverlap
                    ? ($row->status === 'approved' ? 'approved' : 'pending')
                    : 'not_required',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('facility_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('facility_reservations', 'venue_reviewed_by')) {
                $table->dropConstrainedForeignId('venue_reviewed_by');
            }
            foreach (['venue_rejection_reason', 'venue_reviewed_at', 'venue_status', 'is_pre_plotted'] as $column) {
                if (Schema::hasColumn('facility_reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
