<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.5: lessons gain a lifecycle (draft, published, archived) and authorship.
 * Existing lessons are published, with published_at taken from their course.
 * Pending edits live in lesson_drafts; every publish writes a lesson_revisions row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'archived'])->default('published')->after('position');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->foreignId('created_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index('status');
        });

        DB::table('lessons')
            ->join('course_chapters', 'course_chapters.id', '=', 'lessons.course_chapter_id')
            ->join('courses', 'courses.id', '=', 'course_chapters.course_id')
            ->whereNull('lessons.published_at')
            ->update(['lessons.published_at' => DB::raw('courses.published_at')]);

        Schema::create('lesson_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lesson_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['lesson_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_revisions');
        Schema::dropIfExists('lesson_drafts');
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
