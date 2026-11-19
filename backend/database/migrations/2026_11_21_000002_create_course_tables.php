<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('summary', 300);
            $table->text('description');
            $table->unsignedTinyInteger('difficulty'); // 1 beginner, 2 intermediate, 3 advanced
            $table->string('thumbnail_path')->nullable();
            $table->json('objectives');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'domain_id', 'difficulty']);
            $table->index(['is_published', 'published_at']);
        });

        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_course_id')->constrained('courses')->cascadeOnDelete();
            $table->primary(['course_id', 'prerequisite_course_id']);
        });

        Schema::create('course_skills', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'skill_id']);
        });

        Schema::create('course_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['course_id', 'position']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_chapter_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('summary', 300);
            $table->json('objectives');
            $table->json('key_takeaways');
            $table->json('resources');
            $table->unsignedSmallInteger('estimated_minutes');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['course_chapter_id', 'position']);
        });

        Schema::create('lesson_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->enum('type', ['prose', 'callout', 'steps', 'code', 'diagram', 'visual_3d']);
            $table->string('heading', 160)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['lesson_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_sections');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_chapters');
        Schema::dropIfExists('course_skills');
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('courses');
    }
};
