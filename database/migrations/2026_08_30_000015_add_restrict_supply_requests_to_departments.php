<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Departments flagged here (e.g. Facilities Management Office) have
     * requestors who only ever need Activity Proposals / Facility
     * Reservations — they should never see the OPEX Inventory or
     * Requisitions tabs. This is a per-department toggle so Super Admin can
     * apply the same restriction to any other department later without a
     * code change.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('restrict_supply_requests')->default(false)->after('opex_limit');
        });

        // Best-effort: if a "Facilities Management Office" department already
        // exists in this database, flag it automatically so nothing needs to
        // be done manually right after this migration runs. Super Admin can
        // still flip it back off (or on for any other department) in
        // Reference Data at any time.
        DB::table('departments')
            ->where('name', 'Facilities Management Office')
            ->update(['restrict_supply_requests' => true]);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('restrict_supply_requests');
        });
    }
};
