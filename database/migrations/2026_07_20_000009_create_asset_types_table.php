<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_category_id')->constrained('item_categories')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['item_category_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_types');
    }
};
