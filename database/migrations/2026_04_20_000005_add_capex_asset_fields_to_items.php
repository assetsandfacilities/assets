<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'acquisition_date')) {
                $table->date('acquisition_date')->nullable()->after('image_path');
            }
            if (!Schema::hasColumn('items', 'assigned_department')) {
                $table->string('assigned_department')->nullable()->after('acquisition_date');
            }
            if (!Schema::hasColumn('items', 'asset_type_name')) {
                $table->string('asset_type_name')->nullable()->after('assigned_department');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            foreach (['asset_type_name', 'assigned_department', 'acquisition_date'] as $column) {
                if (Schema::hasColumn('items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
