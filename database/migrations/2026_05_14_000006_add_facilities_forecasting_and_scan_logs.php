<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('facilities')) {
            Schema::create('facilities', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('location')->nullable();
                $table->integer('capacity')->default(0);
                $table->text('resources')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('facility_reservations')) {
            Schema::create('facility_reservations', function (Blueprint $table) {
                $table->id();
                $table->string('reservation_no')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
                $table->string('title');
                $table->text('purpose')->nullable();
                $table->text('resources_needed')->nullable();
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('status')->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->index(['facility_id', 'start_at', 'end_at']);
            });
        }

        if (!Schema::hasTable('inventory_usage_logs')) {
            Schema::create('inventory_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
                $table->date('usage_date');
                $table->integer('quantity_used');
                $table->string('source')->default('manual');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->index(['item_id', 'usage_date']);
            });
        }

        if (!Schema::hasTable('asset_scan_logs')) {
            Schema::create('asset_scan_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('expected_room')->nullable();
                $table->string('scanned_room')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('status')->default('matched');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['item_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_scan_logs');
        Schema::dropIfExists('inventory_usage_logs');
        Schema::dropIfExists('facility_reservations');
        Schema::dropIfExists('facilities');
    }
};
