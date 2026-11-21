<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson exercises (Phase 5, ARCHITECTURE D29 to D31). Owned by the Learning module and
 * loaded from seeders/content/exercises/*.php. Questions are archived, never deleted, because
 * attempt answers refer to them. Answer keys live in `answer` and are never serialized
 * before submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pass_percent')->default(70);
            $table->timestamps();
        });

        Schema::create('exercise_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->unsignedSmallInteger('position');
            $table->string('type', 20);
            $table->text('prompt');
            $table->json('payload');
            $table->json('answer');
            $table->text('explanation');
            // The theory reference (D20, D30): a section deleted while an active question uses it is
            // refused; an archived question's reference is simply cleared.
            $table->foreignId('lesson_section_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['exercise_id', 'key']);
            $table->index(['exercise_id', 'is_archived', 'position']);
        });

        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score_percent');
            $table->boolean('passed');
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'exercise_id', 'passed']);
        });

        Schema::create('exercise_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_question_id')->constrained()->cascadeOnDelete();
            $table->json('response')->nullable();
            $table->boolean('is_correct');
            $table->timestamps();

            $table->unique(['exercise_attempt_id', 'exercise_question_id'], 'exercise_attempt_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_attempt_answers');
        Schema::dropIfExists('exercise_attempts');
        Schema::dropIfExists('exercise_questions');
        Schema::dropIfExists('exercises');
    }
};
