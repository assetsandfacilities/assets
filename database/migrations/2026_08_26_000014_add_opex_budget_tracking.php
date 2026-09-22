<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Departments' capex_limit / opex_limit were plain integers (whole pesos only).
        // Budget amounts need centavo precision, so switch them to decimal(12,2).
        Schema::table('departments', function (Blueprint $table) {
            $table->decimal('capex_limit', 12, 2)->default(0)->change();
            $table->decimal('opex_limit', 12, 2)->default(0)->change();
        });

        // Snapshot the unit price and computed line amount on each requisition item
        // at the moment it is submitted, so a later change to an item's unit_price
        // never rewrites the amount that was already charged against a department's
        // OPEX budget.
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->default(0)->after('quantity_approved');
            $table->decimal('total_amount', 12, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'total_amount']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->integer('capex_limit')->default(0)->change();
            $table->integer('opex_limit')->default(0)->change();
        });
    }
};
