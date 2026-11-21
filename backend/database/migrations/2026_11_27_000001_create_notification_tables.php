<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 notifications (D34): Laravel's database notifications, per-type preferences (a row
 * only once a learner changes a type; defaults live in code) and the keys of one-off sends
 * (a milestone, a day's reminder, a lesson recommendation), so each goes out once even if the
 * learner deletes the notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['recommendation', 'lesson_completed', 'milestone', 'reminder', 'assessment_result']);
            $table->boolean('in_app');
            $table->boolean('email');
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });

        Schema::create('notification_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_sends');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
