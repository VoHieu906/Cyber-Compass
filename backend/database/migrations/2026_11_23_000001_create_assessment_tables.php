<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Knowledge assessment (Phase 4, ARCHITECTURE §6.1 and D24). The question bank is loaded from
| database/seeders/content/assessment.php by the Assessment module: questions are matched by a
| stable `key` and archived, never deleted, because answers refer to them.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->text('description');
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->string('key', 120); // stable identity in the content file
            $table->unsignedTinyInteger('difficulty'); // 1 to 3; also the scoring weight
            $table->text('prompt');
            $table->text('code')->nullable(); // optional snippet shown under the prompt
            $table->text('explanation');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['assessment_id', 'key']);
            $table->index(['assessment_id', 'is_archived', 'position']);
        });

        Schema::create('assessment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 300);
            $table->boolean('is_correct')->default(false); // never serialized before submission
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['assessment_question_id', 'position']);
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('score_percent')->nullable(); // immutable snapshot, set on submit
            // 1 while the attempt is open, NULL once submitted: the unique index below allows
            // many submitted attempts but only one open attempt per user and assessment.
            $table->boolean('open_marker')->nullable()->storedAs('if(submitted_at is null, 1, null)');
            $table->timestamps();

            $table->index(['user_id', 'submitted_at']);
            $table->unique(['user_id', 'assessment_id', 'open_marker']);
        });

        // One row per question the attempt drew, created when the attempt starts. An unanswered
        // question has no option; it counts as incorrect once the attempt is submitted.
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_option_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_correct')->nullable(); // set on submit
            $table->timestamps();

            $table->unique(['assessment_attempt_id', 'assessment_question_id'], 'assessment_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_options');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
    }
};
