<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt e-signatures at the application layer before they are stored in the
 * database. Passwords and voucher codes are already one-way hashed and should
 * not be reversibly encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'signature_data')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('signature_data')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $value = (string) $user->signature_data;
                    if ($value === '' || $this->isEncrypted($value)) {
                        continue;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'signature_data' => Crypt::encryptString($value),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'signature_data')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('signature_data')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $value = (string) $user->signature_data;
                    try {
                        $plain = Crypt::decryptString($value);
                    } catch (DecryptException) {
                        continue;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'signature_data' => $plain,
                    ]);
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
