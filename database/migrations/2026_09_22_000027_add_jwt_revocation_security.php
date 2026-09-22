<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('revoked_jwt_tokens')) {
            Schema::create('revoked_jwt_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('jti', 64)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at');
                $table->string('reason', 100)->nullable();
                $table->timestamps();
                $table->index('expires_at');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'jwt_revoked_before')) {
                $table->timestamp('jwt_revoked_before')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'jwt_revoked_before')) {
                $table->dropColumn('jwt_revoked_before');
            }
        });

        Schema::dropIfExists('revoked_jwt_tokens');
    }
};
