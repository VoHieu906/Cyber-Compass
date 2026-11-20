<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Learning goals and personal roadmaps (Phase 4 Part C, ARCHITECTURE §6.2 to §6.3).
| A path is a snapshot of topics; node status is computed on read from lesson progress and is
| never stored (D4). Progress lives in lesson_progress, so it carries over between versions.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('career_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('self_level', ['beginner', 'intermediate', 'advanced'])->nullable();
            $table->unsignedSmallInteger('weekly_minutes')->nullable();
            $table->timestamps();
        });

        // Areas of interest.
        Schema::create('learning_goal_domains', function (Blueprint $table) {
            $table->foreignId('learning_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->primary(['learning_goal_id', 'domain_id']);
        });

        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('version');
            $table->enum('status', ['active', 'archived']);
            $table->timestamp('generated_at');
            // 1 while active, NULL once archived: the unique index allows one active path per user.
            $table->boolean('active_marker')->nullable()->storedAs("if(status = 'active', 1, null)");
            $table->timestamps();

            $table->unique(['user_id', 'version']);
            $table->unique(['user_id', 'active_marker']);
        });

        Schema::create('learning_path_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->unsignedSmallInteger('position');
            $table->enum('recommendation', ['required', 'review']);
            $table->string('reason_key', 40); // rendered through i18n
            $table->json('reason_params');
            $table->timestamps();

            $table->unique(['learning_path_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_nodes');
        Schema::dropIfExists('learning_paths');
        Schema::dropIfExists('learning_goal_domains');
        Schema::dropIfExists('learning_goals');
    }
};
