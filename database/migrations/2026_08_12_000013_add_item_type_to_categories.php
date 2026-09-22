<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets Super Admin scope each Category to CAPEX only, OPEX only, or BOTH -- so the
// Category picker on the CAPEX tab only shows CAPEX (or shared) categories, and the
// OPEX tab only shows OPEX (or shared) categories, instead of one mixed list.
// Existing categories default to 'BOTH' so nothing already in use disappears.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('item_categories', 'item_type')) {
                $table->string('item_type', 10)->default('BOTH')->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            if (Schema::hasColumn('item_categories', 'item_type')) {
                $table->dropColumn('item_type');
            }
        });
    }
};
