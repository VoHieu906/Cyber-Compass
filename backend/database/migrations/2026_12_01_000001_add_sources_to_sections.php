<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6 (D68): a section may list the sources behind its facts. Validated once in the block-document
 * core, never in block schemas. Expand-only: older code ignores the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lesson_sections', 'post_sections'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('sources')->nullable()->after('payload');
            });
        }
    }

    public function down(): void
    {
        foreach (['lesson_sections', 'post_sections'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('sources'));
        }
    }
};
