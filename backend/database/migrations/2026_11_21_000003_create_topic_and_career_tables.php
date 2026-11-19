<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roadmap building blocks: a topic groups lessons and may depend on other topics.
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('summary', 300);
            $table->unsignedTinyInteger('level'); // 1 to 3
            $table->timestamps();
        });

        Schema::create('topic_prerequisites', function (Blueprint $table) {
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_topic_id')->constrained('topics')->cascadeOnDelete();
            $table->primary(['topic_id', 'prerequisite_topic_id']);
        });

        Schema::create('topic_lessons', function (Blueprint $table) {
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_required')->default(true);
            $table->primary(['topic_id', 'lesson_id']);
        });

        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 120);
            $table->string('summary', 300);
            $table->text('description');
            $table->json('responsibilities');
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('career_domains', function (Blueprint $table) {
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('required_level'); // 1 to 3
            $table->primary(['career_id', 'domain_id']);
        });

        Schema::create('career_skills', function (Blueprint $table) {
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['career_id', 'skill_id']);
        });

        Schema::create('career_topics', function (Blueprint $table) {
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->unsignedSmallInteger('position');
            $table->primary(['career_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_topics');
        Schema::dropIfExists('career_skills');
        Schema::dropIfExists('career_domains');
        Schema::dropIfExists('careers');
        Schema::dropIfExists('topic_lessons');
        Schema::dropIfExists('topic_prerequisites');
        Schema::dropIfExists('topics');
    }
};
