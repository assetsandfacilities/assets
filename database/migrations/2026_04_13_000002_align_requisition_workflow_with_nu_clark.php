<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'approver_type')) {
                $table->string('approver_type')->nullable()->after('role');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'room_assigned')) {
                $table->string('room_assigned')->nullable()->after('qr_value');
            }
        });

        Schema::table('requisitions', function (Blueprint $table) {
            if (!Schema::hasColumn('requisitions', 'branch')) {
                $table->string('branch')->nullable()->after('department_id');
            }
            if (!Schema::hasColumn('requisitions', 'charge_to_budget_item')) {
                $table->string('charge_to_budget_item')->nullable()->after('branch');
            }
            if (!Schema::hasColumn('requisitions', 'csf_no')) {
                $table->string('csf_no')->nullable()->after('charge_to_budget_item');
            }
            if (!Schema::hasColumn('requisitions', 'requested_by_name')) {
                $table->string('requested_by_name')->nullable()->after('csf_no');
            }
            if (!Schema::hasColumn('requisitions', 'checked_by_name')) {
                $table->string('checked_by_name')->nullable()->after('requested_by_name');
            }
            if (!Schema::hasColumn('requisitions', 'approved_by_name')) {
                $table->string('approved_by_name')->nullable()->after('checked_by_name');
            }
            if (!Schema::hasColumn('requisitions', 'asset_reviewed_by')) {
                $table->foreignId('asset_reviewed_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('requisitions', 'asset_reviewed_at')) {
                $table->timestamp('asset_reviewed_at')->nullable()->after('asset_reviewed_by');
            }
            if (!Schema::hasColumn('requisitions', 'dean_approved_by')) {
                $table->foreignId('dean_approved_by')->nullable()->after('asset_reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('requisitions', 'dean_approved_at')) {
                $table->timestamp('dean_approved_at')->nullable()->after('dean_approved_by');
            }
            if (!Schema::hasColumn('requisitions', 'executive_approved_by')) {
                $table->foreignId('executive_approved_by')->nullable()->after('dean_approved_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('requisitions', 'executive_approved_at')) {
                $table->timestamp('executive_approved_at')->nullable()->after('executive_approved_by');
            }
            if (!Schema::hasColumn('requisitions', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('executive_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            foreach (['asset_reviewed_by', 'dean_approved_by', 'executive_approved_by'] as $column) {
                if (Schema::hasColumn('requisitions', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach ([
                'branch', 'charge_to_budget_item', 'csf_no', 'requested_by_name', 'checked_by_name',
                'approved_by_name', 'asset_reviewed_at', 'dean_approved_at', 'executive_approved_at', 'finalized_at'
            ] as $column) {
                if (Schema::hasColumn('requisitions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'room_assigned')) {
                $table->dropColumn('room_assigned');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'approver_type')) {
                $table->dropColumn('approver_type');
            }
        });
    }
};
