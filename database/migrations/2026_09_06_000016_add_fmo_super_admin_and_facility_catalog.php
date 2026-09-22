<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration for the Asset Management / FMO Super Admin separation.
 *
 * Nothing here drops or rewrites existing data:
 *  - facility_items is a brand new catalogue table (venue items + services)
 *    that replaces the previously hard-coded checklist on the reservation form.
 *  - equipment_details / resources_details are new nullable JSON columns that
 *    sit BESIDE the existing comma-separated equipment_needed /
 *    resources_needed strings, so every old record keeps rendering exactly as
 *    before while new records also carry per-item quantities.
 *  - users.role stays a plain string column, so the new "fmo_super_admin"
 *    value needs no schema change at all.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('facility_items')) {
            Schema::create('facility_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type')->default('item'); // item | service
                $table->string('unit')->nullable();
                $table->text('description')->nullable();
                $table->boolean('allows_quantity')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('activity_proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_proposals', 'equipment_details')) {
                $table->text('equipment_details')->nullable()->after('equipment_needed');
            }
            if (!Schema::hasColumn('activity_proposals', 'equipment_other_note')) {
                $table->string('equipment_other_note', 1000)->nullable()->after('equipment_details');
            }
        });

        Schema::table('facility_reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('facility_reservations', 'resources_details')) {
                $table->text('resources_details')->nullable()->after('resources_needed');
            }
            if (!Schema::hasColumn('facility_reservations', 'resources_other_note')) {
                $table->string('resources_other_note', 1000)->nullable()->after('resources_details');
            }
        });

        // Seed the catalogue only when it is still empty, so re-running
        // migrations on an existing install never duplicates or resets rows an
        // FMO Super Admin has already edited.
        if (DB::table('facility_items')->count() === 0) {
            $now = now();
            $rows = [];
            $defaults = [
                // [name, type, unit, allows_quantity]
                ['Table', 'item', 'pc', true],
                ['Chairs', 'item', 'pc', true],
                ['Sound System', 'item', 'set', true],
                ['Speaker', 'item', 'pc', true],
                ['Projector', 'item', 'pc', true],
                ['Extension Cord', 'item', 'pc', true],
                ['Microphone', 'item', 'pc', true],
                ['Flag', 'item', 'pc', true],
                ['Whiteboard', 'item', 'pc', true],
                ['ITSO Services', 'service', 'personnel', true],
                ['Technical Assistance', 'service', 'personnel', true],
                ['Audio / Visual Support', 'service', 'personnel', true],
                ['Janitors', 'service', 'personnel', true],
                ['Electricians', 'service', 'personnel', true],
            ];
            foreach ($defaults as $i => [$name, $type, $unit, $allowsQty]) {
                $rows[] = [
                    'name' => $name,
                    'type' => $type,
                    'unit' => $unit,
                    'description' => null,
                    'allows_quantity' => $allowsQty,
                    'is_active' => true,
                    'sort_order' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('facility_items')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            foreach (['equipment_details', 'equipment_other_note'] as $column) {
                if (Schema::hasColumn('activity_proposals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('facility_reservations', function (Blueprint $table) {
            foreach (['resources_details', 'resources_other_note'] as $column) {
                if (Schema::hasColumn('facility_reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('facility_items');
    }
};
