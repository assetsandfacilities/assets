<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('floors')) {
            Schema::create('floors', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique(); // e.g. "4th Floor"
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('rooms')) {
            Schema::create('rooms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('floor_id')->constrained('floors')->cascadeOnDelete();
                $table->string('name'); // e.g. "719" or "Server Room"
                $table->string('code')->nullable();
                $table->timestamps();
                $table->unique(['floor_id', 'name']);
            });
        }

        // Items keep their existing text columns (room_assigned, floor) for backward
        // compatibility with everything that already reads them (mismatch detection,
        // reports, the mobile API, asset tag generation) -- these new FK columns become
        // the source of truth going forward and are kept in sync whenever room_id/floor_id
        // is set through the form.
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'floor_id')) {
                $table->foreignId('floor_id')->nullable()->after('floor')->constrained('floors')->nullOnDelete();
            }
            if (!Schema::hasColumn('items', 'room_id')) {
                $table->foreignId('room_id')->nullable()->after('room_assigned')->constrained('rooms')->nullOnDelete();
            }
            if (!Schema::hasColumn('items', 'assigned_department_id')) {
                $table->foreignId('assigned_department_id')->nullable()->after('assigned_department')->constrained('departments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'assigned_department_id')) {
                $table->dropConstrainedForeignId('assigned_department_id');
            }
            if (Schema::hasColumn('items', 'room_id')) {
                $table->dropConstrainedForeignId('room_id');
            }
            if (Schema::hasColumn('items', 'floor_id')) {
                $table->dropConstrainedForeignId('floor_id');
            }
        });
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('floors');
    }
};
