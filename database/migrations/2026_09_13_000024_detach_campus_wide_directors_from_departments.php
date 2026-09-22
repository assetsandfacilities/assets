<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Academic Director and Executive Director are campus-wide roles at NU Clark --
 * one of each, neither belonging to a department. Older seeded accounts were
 * created with a department_id anyway, which is what made them show up inside
 * the department-based approver picker and forced requestors to "choose"
 * between a list of one.
 *
 * Signed and submitted documents are untouched: this only clears the
 * department on the user accounts themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'approver')
            ->whereIn('approver_type', ['academic_director', 'executive'])
            ->update(['department_id' => null]);

        // Any pending voucher for those roles should not carry a department
        // into the account it creates at signup either.
        if (DB::getSchemaBuilder()->hasTable('access_vouchers')) {
            DB::table('access_vouchers')
                ->where('voucher_type', 'approver')
                ->whereIn('approver_type', ['academic_director', 'executive'])
                ->update(['department_id' => null]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: the previous department values were
        // incorrect data, not a configuration worth restoring.
    }
};
