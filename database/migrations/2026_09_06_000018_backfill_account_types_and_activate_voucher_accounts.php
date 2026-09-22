<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finalize the voucher-based account flow.
 *
 * 1) Voucher-created Staff / Organization / Approver accounts are active
 *    immediately; the voucher itself is Asset Management's authorization.
 * 2) Older rows that predate account_type/access_scope are classified so the
 *    Users table always shows a real account type instead of a "Legacy" label.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'account_type') || !Schema::hasColumn('users', 'access_scope')) {
            return;
        }

        // Explicit role mappings are deterministic.
        DB::table('users')->whereNull('account_type')->where('role', 'super_admin')->update([
            'account_type' => 'asset_super_admin', 'access_scope' => 'asset',
        ]);
        DB::table('users')->whereNull('account_type')->where('role', 'admin')->update([
            'account_type' => 'asset_admin', 'access_scope' => 'asset',
        ]);
        DB::table('users')->whereNull('account_type')->where('role', 'approver')->update([
            'account_type' => 'approver', 'access_scope' => 'asset',
        ]);
        DB::table('users')->whereNull('account_type')->where('role', 'fmo_super_admin')->update([
            'account_type' => 'fmo_super_admin', 'access_scope' => 'fmo',
        ]);
        DB::table('users')->whereNull('account_type')->where('role', 'fmo')->update([
            'account_type' => 'fmo_staff', 'access_scope' => 'fmo',
        ]);
        DB::table('users')->whereNull('account_type')->where('role', 'housekeeping')->update([
            'account_type' => 'housekeeping',
        ]);

        // For old requestors, infer Facilities-only users from their department
        // flag or prior Facilities/Activity usage. Remaining requestors are
        // treated as Staff because old data had no Staff-vs-Organization field.
        $facilityRequestorIds = DB::table('users')
            ->whereNull('account_type')
            ->where('role', 'requestor')
            ->where(function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('departments')
                        ->whereColumn('departments.id', 'users.department_id')
                        ->where('departments.restrict_supply_requests', true);
                });

                if (Schema::hasTable('facility_reservations')) {
                    $q->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('facility_reservations')
                            ->whereColumn('facility_reservations.user_id', 'users.id');
                    });
                }

                if (Schema::hasTable('activity_proposals')) {
                    $q->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('activity_proposals')
                            ->whereColumn('activity_proposals.user_id', 'users.id');
                    });
                }
            })
            ->pluck('id');

        if ($facilityRequestorIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $facilityRequestorIds)->update([
                'account_type' => 'student', 'access_scope' => 'fmo',
            ]);
        }

        DB::table('users')->whereNull('account_type')->where('role', 'requestor')->update([
            'account_type' => 'staff', 'access_scope' => 'asset',
        ]);

        // Any previously-created voucher account that is still pending becomes
        // active now, matching the new no-second-approval workflow.
        DB::table('users')
            ->whereIn('account_type', ['staff', 'organization', 'approver'])
            ->where('is_approved', false)
            ->update([
                'is_approved' => true,
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data classification/activation is intentionally not reversed.
    }
};
