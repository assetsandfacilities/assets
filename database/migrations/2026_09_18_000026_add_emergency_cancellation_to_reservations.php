<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 | Emergency cancellation and rebooking for confirmed venue bookings.
 |
 | A fully approved activity can still lose its venue at short notice when the
 | administration needs the space. Rejecting the booking would be wrong: the
 | Adviser, Dean, SDAO and the two directors have already signed, and none of
 | that changed. So the booking is not rejected -- the SLOT is released, the
 | signed approval trail is left exactly as it stands, and the requestor is
 | asked to pick a new date or a different venue. Their choice comes back to
 | the Facilities Office for a decision on the new slot alone.
 |
 | Columns:
 |   emergency_*   what the office did, why, and which options it is offering.
 |   rebooking_status   where the booking is in that conversation:
 |                      awaiting_requestor -> awaiting_fmo -> rebooked
 |                      (or declined, which sends it back to the requestor).
 |   proposed_*    what the requestor asked for, pending the office's decision.
 |   original_*    the slot the booking held before the emergency, kept so the
 |                 detail page can show what it was moved from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_reservations', function (Blueprint $table) {
            $table->timestamp('emergency_cancelled_at')->nullable()->after('rejection_reason');
            $table->foreignId('emergency_cancelled_by')->nullable()->after('emergency_cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->text('emergency_reason')->nullable()->after('emergency_cancelled_by');
            // 'both', 'date' or 'venue': what the requestor may change.
            $table->string('emergency_options', 20)->nullable()->after('emergency_reason');

            $table->string('rebooking_status', 30)->nullable()->after('emergency_options');
            $table->string('proposed_kind', 10)->nullable()->after('rebooking_status');
            $table->timestamp('proposed_start_at')->nullable()->after('proposed_kind');
            $table->timestamp('proposed_end_at')->nullable()->after('proposed_start_at');
            $table->foreignId('proposed_facility_id')->nullable()->after('proposed_end_at')
                ->constrained('facilities')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable()->after('proposed_facility_id');
            $table->text('rebooking_decision_note')->nullable()->after('proposed_at');
            $table->timestamp('rebooking_decided_at')->nullable()->after('rebooking_decision_note');
            $table->foreignId('rebooking_decided_by')->nullable()->after('rebooking_decided_at')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('original_start_at')->nullable()->after('rebooking_decided_by');
            $table->timestamp('original_end_at')->nullable()->after('original_start_at');
            $table->foreignId('original_facility_id')->nullable()->after('original_end_at')
                ->constrained('facilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('facility_reservations', function (Blueprint $table) {
            foreach ([
                'emergency_cancelled_by' => 'emergency_cancelled_by_foreign',
                'proposed_facility_id' => 'proposed_facility_id_foreign',
                'rebooking_decided_by' => 'rebooking_decided_by_foreign',
                'original_facility_id' => 'original_facility_id_foreign',
            ] as $column => $_) {
                try {
                    $table->dropConstrainedForeignId($column);
                } catch (\Throwable $e) {
                    // SQLite deployments drop the column without a named key.
                }
            }

            $table->dropColumn([
                'emergency_cancelled_at', 'emergency_reason', 'emergency_options',
                'rebooking_status', 'proposed_kind', 'proposed_start_at', 'proposed_end_at',
                'proposed_at', 'rebooking_decision_note', 'rebooking_decided_at',
                'original_start_at', 'original_end_at',
            ]);
        });
    }
};
