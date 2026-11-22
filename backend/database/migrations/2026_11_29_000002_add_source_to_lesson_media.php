<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The content-file image an imported media row came from (ARCHITECTURE D56), so content:export
 * can write the file reference back. Null for uploads. Additive and nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            $table->string('source', 255)->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
