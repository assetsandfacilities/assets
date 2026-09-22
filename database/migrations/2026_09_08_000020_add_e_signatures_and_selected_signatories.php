<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function dummySignature(string $name): string
    {
        $safe = htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="520" height="150" viewBox="0 0 520 150">'
            . '<rect width="100%" height="100%" fill="white" fill-opacity="0"/>'
            . '<text x="18" y="88" font-family="cursive" font-size="44" font-style="italic" fill="#17213c">'.$safe.'</text>'
            . '<path d="M18 108 C145 128 330 95 500 111" fill="none" stroke="#17213c" stroke-width="2"/>'
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'signature_data')) {
                $table->longText('signature_data')->nullable();
            }
            if (!Schema::hasColumn('users', 'signature_updated_at')) {
                $table->timestamp('signature_updated_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'signature_updated_by')) {
                $table->foreignId('signature_updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('signature_audits')) {
            Schema::create('signature_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source')->default('super_admin');
                $table->string('previous_hash', 64)->nullable();
                $table->string('new_hash', 64)->nullable();
                $table->timestamp('changed_at');
                $table->timestamps();
                $table->index(['user_id', 'changed_at']);
            });
        }

        Schema::table('requisitions', function (Blueprint $table) {
            if (!Schema::hasColumn('requisitions', 'dean_approver_id')) {
                $table->foreignId('dean_approver_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('requisitions', 'executive_approver_id')) {
                $table->foreignId('executive_approver_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // Existing hard-coded approvers / administrators get safe dummy signatures
        // so old seeded accounts can immediately participate in printable approvals.
        if (Schema::hasColumn('users', 'signature_data')) {
            $users = DB::table('users')
                ->whereIn('role', ['super_admin', 'admin', 'approver', 'fmo_super_admin', 'fmo'])
                ->whereNull('signature_data')
                ->select('id', 'name')
                ->get();
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'signature_data' => $this->dummySignature((string) $user->name),
                    'signature_updated_at' => now(),
                ]);
            }
        }

        // Preserve existing requisition routing by choosing a sensible assigned
        // approver for old requests that pre-date per-request signatory selection.
        if (Schema::hasColumn('requisitions', 'dean_approver_id')) {
            $defaultDean = DB::table('users')->where('role', 'approver')->where('approver_type', 'dean')->value('id');
            $defaultExecutive = DB::table('users')->where('role', 'approver')->where('approver_type', 'executive')->value('id');
            if ($defaultDean) {
                DB::table('requisitions')->whereNull('dean_approver_id')->update(['dean_approver_id' => $defaultDean]);
            }
            if ($defaultExecutive) {
                DB::table('requisitions')->whereNull('executive_approver_id')->update(['executive_approver_id' => $defaultExecutive]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            foreach (['dean_approver_id', 'executive_approver_id'] as $column) {
                if (Schema::hasColumn('requisitions', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::dropIfExists('signature_audits');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'signature_updated_by')) {
                $table->dropConstrainedForeignId('signature_updated_by');
            }
            foreach (['signature_data', 'signature_updated_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
