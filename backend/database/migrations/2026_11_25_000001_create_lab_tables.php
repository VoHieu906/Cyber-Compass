<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Practical labs (Phase 5, ARCHITECTURE §7 and D28). A lab names a server-side engine and a
 * scenario; the scenario's checkpoint answers never leave the server. Each learner has one
 * attempt per lab, whose engine state is guarded by an optimistic lock (`state_version`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->string('summary', 500);
            $table->text('briefing');
            $table->foreignId('domain_id')->constrained();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('engine', 40);
            $table->string('provider', 40)->default('simulated');
            $table->json('scenario');
            $table->unsignedTinyInteger('difficulty');
            $table->unsignedSmallInteger('estimated_minutes');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['is_archived', 'position']);
        });

        Schema::create('lab_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->json('state');
            $table->unsignedInteger('state_version')->default(1);
            $table->unsignedInteger('action_count')->default(0);
            $table->json('checkpoints_passed');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lab_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_attempts');
        Schema::dropIfExists('labs');
    }
};
