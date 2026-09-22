<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'account_type')) {
                $table->string('account_type')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'access_scope')) {
                $table->string('access_scope')->nullable()->after('account_type');
            }
        });

        if (!Schema::hasTable('access_vouchers')) {
            Schema::create('access_vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('code_hash', 64)->unique();
                $table->string('code_hint', 32)->nullable();
                $table->string('voucher_type'); // requestor | approver
                $table->string('approver_type')->nullable();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['voucher_type', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_vouchers');
        Schema::table('users', function (Blueprint $table) {
            foreach (['access_scope', 'account_type'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
