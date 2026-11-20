<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 3.5 (D20, D18): sections get a stable UUID that survives reordering and edits,
 * and `type` becomes a string validated by the block registry instead of a MySQL enum,
 * so adding a block type needs no ALTER. Expand-only: safe while old code still runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_sections', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('lesson_sections')->whereNull('uuid')->orderBy('id')->lazyById()->each(
            fn ($row) => DB::table('lesson_sections')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]),
        );

        Schema::table('lesson_sections', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
            $table->string('type', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_sections', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
