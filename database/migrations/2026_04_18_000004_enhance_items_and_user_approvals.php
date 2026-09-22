<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'specifications')) {
                $table->text('specifications')->nullable()->after('description');
            }
            if (!Schema::hasColumn('items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->default(0)->after('unit');
            }
            if (!Schema::hasColumn('items', 'brand')) {
                $table->string('brand')->nullable()->after('unit_price');
            }
            if (!Schema::hasColumn('items', 'availability_status')) {
                $table->string('availability_status')->default('Available')->after('low_stock_threshold');
            }
            if (!Schema::hasColumn('items', 'image_path')) {
                $table->text('image_path')->nullable()->after('room_assigned');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_approved')) {
                $table->boolean('is_approved')->default(false)->after('role');
            }
            if (!Schema::hasColumn('users', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('is_approved');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            foreach (['specifications', 'unit_price', 'brand', 'availability_status', 'image_path'] as $column) {
                if (Schema::hasColumn('items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['is_approved', 'approved_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
