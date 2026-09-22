<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mobile scanning flow changed: the housekeeper now picks the Floor + Room they are
// physically standing in BEFORE scanning the QR code (instead of typing a free-text
// room name after scanning, and instead of GPS coordinates). These two FK columns are
// the real source of truth for the match/mismatch decision going forward. The old
// text columns (expected_room, scanned_room) and the old GPS columns (latitude,
// longitude, distance_meters) are kept in the table so existing/historical scan logs
// still display correctly, but they are no longer written to by new scans.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('asset_scan_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_scan_logs', 'scanned_floor_id')) {
                $table->foreignId('scanned_floor_id')->nullable()->after('item_id')->constrained('floors')->nullOnDelete();
            }
            if (!Schema::hasColumn('asset_scan_logs', 'scanned_room_id')) {
                $table->foreignId('scanned_room_id')->nullable()->after('scanned_floor_id')->constrained('rooms')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_scan_logs', function (Blueprint $table) {
            if (Schema::hasColumn('asset_scan_logs', 'scanned_room_id')) {
                $table->dropConstrainedForeignId('scanned_room_id');
            }
            if (Schema::hasColumn('asset_scan_logs', 'scanned_floor_id')) {
                $table->dropConstrainedForeignId('scanned_floor_id');
            }
        });
    }
};
