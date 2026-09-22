<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unify Asset Management requestor registration.
 *
 * Staff and Organization are no longer separate Asset account types. Existing
 * Asset-side Staff/Organization users and voucher records are normalized to
 * the single Requestor type so the UI and authorization remain consistent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'account_type')) {
            DB::table('users')
                ->where('access_scope', 'asset')
                ->where('role', 'requestor')
                ->whereIn('account_type', ['staff', 'organization'])
                ->update(['account_type' => 'requestor']);
        }

        if (Schema::hasTable('access_vouchers')) {
            DB::table('access_vouchers')
                ->whereIn('voucher_type', ['staff', 'organization'])
                ->update(['voucher_type' => 'requestor']);
        }
    }

    public function down(): void
    {
        // The former Staff-vs-Organization distinction cannot be reconstructed
        // reliably after unification, so this data migration is not reversed.
    }
};
