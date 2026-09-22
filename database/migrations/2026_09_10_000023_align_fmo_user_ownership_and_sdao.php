<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align existing production rows with the clarified account ownership rules.
     *
     * - Housekeeping accounts are created/managed only by FMO Super Admin, but
     *   retain Asset Scanner access because that is their operational tool.
     * - SDAO is a school-wide office, so its approver accounts are not tied to
     *   a department for Signature Routing.
     *
     * Uses portable UPDATE statements compatible with MySQL/TiDB.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'housekeeping')
            ->update([
                'account_type' => 'housekeeping',
                'access_scope' => 'asset',
            ]);

        DB::table('users')
            ->where('role', 'approver')
            ->where('approver_type', 'sdao')
            ->update(['department_id' => null]);

        DB::table('users')
            ->where('role', 'fmo')
            ->update([
                'account_type' => 'fmo_staff',
                'access_scope' => 'fmo',
            ]);

        DB::table('users')
            ->where('role', 'fmo_super_admin')
            ->update([
                'account_type' => 'fmo_super_admin',
                'access_scope' => 'fmo',
            ]);
    }

    public function down(): void
    {
        // Intentionally no destructive rollback. The previous department and
        // scope values cannot be reconstructed safely once normalized.
    }
};
