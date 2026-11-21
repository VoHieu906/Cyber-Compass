<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 (D33): the append-only activity log behind streaks, weekly time and the recent feed,
 * and the learner's timezone, which decides what "today" means for streaks and reminders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['lesson_started', 'lesson_completed', 'study_session', 'exercise_submitted', 'assessment_submitted', 'lab_completed']);
            $table->morphs('subject');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('occurred_at');
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn('timezone'));
        Schema::dropIfExists('learning_activities');
    }
};
