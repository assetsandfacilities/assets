<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            if (!Schema::hasColumn('facilities', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location');
            }
            if (!Schema::hasColumn('facilities', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        Schema::table('asset_scan_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_scan_logs', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('asset_scan_logs', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('asset_scan_logs', 'distance_meters')) {
                $table->decimal('distance_meters', 8, 2)->nullable()->after('longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            foreach (['latitude', 'longitude'] as $c) {
                if (Schema::hasColumn('facilities', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('asset_scan_logs', function (Blueprint $table) {
            if (Schema::hasColumn('asset_scan_logs', 'resolved_by')) {
                $table->dropConstrainedForeignId('resolved_by');
            }
            foreach (['resolved_at', 'distance_meters'] as $c) {
                if (Schema::hasColumn('asset_scan_logs', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
