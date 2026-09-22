<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_proposals', 'organization_name')) {
                $table->string('organization_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('activity_proposals', 'requester_position')) {
                $table->string('requester_position')->nullable()->after('organization_name');
            }
            if (!Schema::hasColumn('activity_proposals', 'activity_days')) {
                $table->string('activity_days')->nullable()->after('title');
            }
            if (!Schema::hasColumn('activity_proposals', 'speaker_name')) {
                $table->string('speaker_name')->nullable()->after('program_flow');
            }
            if (!Schema::hasColumn('activity_proposals', 'venue_other_note')) {
                $table->string('venue_other_note')->nullable()->after('facility_id');
            }

            // Noted by: Dean/Principal (reuses department_approver_id/department_signed_by) + SDAO (new)
            if (!Schema::hasColumn('activity_proposals', 'sdao_id')) {
                $table->foreignId('sdao_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('sdao_signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sdao_signed_at')->nullable();
            }

            // Reviewed by: Facilities Management (reuses fmo_signed_by, new facilities_mgmt_id) + Academic Director (new)
            if (!Schema::hasColumn('activity_proposals', 'facilities_mgmt_id')) {
                $table->foreignId('facilities_mgmt_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('activity_proposals', 'academic_director_id')) {
                $table->foreignId('academic_director_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('academic_director_signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('academic_director_signed_at')->nullable();
            }

            // Approved by: Executive Director (new final stage, replaces FMO as final locker)
            if (!Schema::hasColumn('activity_proposals', 'executive_director_id')) {
                $table->foreignId('executive_director_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('executive_signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('executive_signed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $cols = ['organization_name','requester_position','activity_days','speaker_name','venue_other_note'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('activity_proposals', $c)) {
                    $table->dropColumn($c);
                }
            }
            foreach (['sdao_id','sdao_signed_by','facilities_mgmt_id','academic_director_id','academic_director_signed_by','executive_director_id','executive_signed_by'] as $c) {
                if (Schema::hasColumn('activity_proposals', $c)) {
                    $table->dropConstrainedForeignId($c);
                }
            }
            foreach (['sdao_signed_at','academic_director_signed_at','executive_signed_at'] as $c) {
                if (Schema::hasColumn('activity_proposals', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
