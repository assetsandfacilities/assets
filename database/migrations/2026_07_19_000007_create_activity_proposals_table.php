<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('activity_proposals')) {
            Schema::create('activity_proposals', function (Blueprint $table) {
                $table->id();
                $table->string('proposal_no')->unique();

                // Requester (student / org representative)
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

                // Routing: Adviser -> Department (Dean) -> FMO
                $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('department_approver_id')->nullable()->constrained('users')->nullOnDelete();

                // Activity details (from the proposal form)
                $table->string('title');
                $table->text('program_flow')->nullable();
                $table->integer('participants_count')->default(0);
                $table->text('equipment_needed')->nullable();

                // Venue / schedule (ties into facility_reservations)
                $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
                $table->dateTime('start_at')->nullable();
                $table->dateTime('end_at')->nullable();
                $table->foreignId('facility_reservation_id')->nullable()->constrained('facility_reservations')->nullOnDelete();

                // Workflow status
                $table->string('status')->default('pending_adviser');
                // pending_adviser | pending_department | pending_fmo | approved | rejected

                // Digital signature trail (login + approve click = signature)
                $table->foreignId('adviser_signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('adviser_signed_at')->nullable();
                $table->foreignId('department_signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('department_signed_at')->nullable();
                $table->foreignId('fmo_signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('fmo_signed_at')->nullable();

                $table->string('rejected_stage')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamps();
            });
        }

        if (Schema::hasTable('facility_reservations') && !Schema::hasColumn('facility_reservations', 'activity_proposal_id')) {
            Schema::table('facility_reservations', function (Blueprint $table) {
                $table->foreignId('activity_proposal_id')->nullable()->after('id')->constrained('activity_proposals')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('facility_reservations', 'activity_proposal_id')) {
            Schema::table('facility_reservations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('activity_proposal_id');
            });
        }
        Schema::dropIfExists('activity_proposals');
    }
};
