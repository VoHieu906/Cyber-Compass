<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The debrief of a lab (F7 Part D fix, D80) is written when an attempt first completes and kept until
 * the learner resets, so it does not change if they undo work afterwards. Nullable, expand-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_attempts', function (Blueprint $table) {
            $table->json('debrief')->nullable()->after('restarted_at');
        });
    }

    public function down(): void
    {
        Schema::table('lab_attempts', function (Blueprint $table) {
            $table->dropColumn('debrief');
        });
    }
};
