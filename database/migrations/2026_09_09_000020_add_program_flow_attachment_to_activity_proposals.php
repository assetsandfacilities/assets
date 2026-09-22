<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Program Flow attachment.
 *
 * The requestor may upload the program flow as a PDF or Word document instead
 * of retyping it. The extracted text is written into the existing program_flow
 * column so every screen that already reads it keeps working unchanged, while
 * these columns keep the original file so the FMO can open the real document.
 *
 * The file itself is stored on the private disk (storage/app), not the public
 * one, and is served through an authorised controller route -- so no
 * `storage:link` is required and proposals are not readable by URL guessing.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_proposals', 'program_flow_path')) {
                $table->string('program_flow_path')->nullable()->after('program_flow');
            }
            if (!Schema::hasColumn('activity_proposals', 'program_flow_filename')) {
                $table->string('program_flow_filename')->nullable()->after('program_flow_path');
            }
            if (!Schema::hasColumn('activity_proposals', 'program_flow_mime')) {
                $table->string('program_flow_mime', 120)->nullable()->after('program_flow_filename');
            }
            if (!Schema::hasColumn('activity_proposals', 'program_flow_extracted')) {
                // Records whether the text in program_flow came from the file
                // or was typed, so the UI can label it honestly.
                $table->boolean('program_flow_extracted')->default(false)->after('program_flow_mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            foreach (['program_flow_path', 'program_flow_filename', 'program_flow_mime', 'program_flow_extracted'] as $column) {
                if (Schema::hasColumn('activity_proposals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
