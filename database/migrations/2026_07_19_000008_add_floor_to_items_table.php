<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('items', 'floor')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('floor', 20)->nullable()->after('room_assigned');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'floor')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('floor');
            });
        }
    }
};
