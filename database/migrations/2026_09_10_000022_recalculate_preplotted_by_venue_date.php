<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time production correction for installations that already ran 000021.
 *
 * Pre-plotted now means: a later active request uses the SAME VENUE on at
 * least one SAME CALENDAR DATE as an earlier active request. Clock time is not
 * considered. This is compatible with TiDB/MySQL through Laravel whereDate().
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('facility_reservations')
            || !Schema::hasColumn('facility_reservations', 'is_pre_plotted')
            || !Schema::hasColumn('facility_reservations', 'venue_status')) {
            return;
        }

        $rows = DB::table('facility_reservations')
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id', 'facility_id', 'start_at', 'end_at', 'status', 'created_at',
                'is_pre_plotted', 'venue_status',
            ]);

        foreach ($rows as $row) {
            $startDate = Carbon::parse($row->start_at)->toDateString();
            $endDate = Carbon::parse($row->end_at)->toDateString();

            $earlier = DB::table('facility_reservations')
                ->where('facility_id', $row->facility_id)
                ->where('id', '!=', $row->id)
                ->where(function ($holding) use ($row) {
                    // Active now, OR rejected only after this later request was
                    // submitted (meaning it was still holding the date then).
                    $holding->whereIn('status', ['pending', 'approved'])
                        ->orWhere(function ($rejectedLater) use ($row) {
                            $rejectedLater->where('status', 'rejected');
                            if ($row->created_at) {
                                $rejectedLater->where('reviewed_at', '>', $row->created_at);
                            }
                        });
                })
                ->whereDate('start_at', '<=', $endDate)
                ->whereDate('end_at', '>=', $startDate)
                ->where(function ($q) use ($row) {
                    if ($row->created_at) {
                        $q->where('created_at', '<', $row->created_at)
                            ->orWhere(function ($tie) use ($row) {
                                $tie->where('created_at', $row->created_at)
                                    ->where('id', '<', $row->id);
                            });
                    } else {
                        $q->where('id', '<', $row->id);
                    }
                })
                ->exists();

            if ($earlier) {
                // Preserve an FMO venue decision that already happened. Newly
                // detected same-date conflicts start Pending unless the whole
                // reservation was already approved.
                $venueStatus = in_array($row->venue_status, ['approved', 'rejected'], true)
                    ? $row->venue_status
                    : ($row->status === 'approved' ? 'approved' : 'pending');

                DB::table('facility_reservations')->where('id', $row->id)->update([
                    'is_pre_plotted' => true,
                    'venue_status' => $venueStatus,
                ]);
            } elseif ((bool) $row->is_pre_plotted) {
                // Never erase a historical pre-plotted flag that was already
                // captured at submission time merely because the earlier request
                // has since been rejected or otherwise resolved.
                DB::table('facility_reservations')->where('id', $row->id)->update([
                    'is_pre_plotted' => true,
                    'venue_status' => $row->venue_status ?: 'pending',
                ]);
            } else {
                DB::table('facility_reservations')->where('id', $row->id)->update([
                    'is_pre_plotted' => false,
                    'venue_status' => 'not_required',
                ]);
            }
        }
    }

    public function down(): void
    {
        // This migration corrects persisted business state. Rolling it back
        // must not guess the old time-overlap state and overwrite live data.
    }
};
