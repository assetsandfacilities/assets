<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 | Polite title (Mr. / Ms. / Mrs.) for accounts whose names are printed on
 | documents.
 |
 | Charge slips and activity proposals print the signatory's name under the
 | e-signature. A bare name reads as unfinished on an official form, so voucher
 | holders choose a title at registration and the Super Admin can set one for
 | accounts created before this change. The column is nullable: students and
 | accounts that skip it simply print the name on its own, exactly as now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('title', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
