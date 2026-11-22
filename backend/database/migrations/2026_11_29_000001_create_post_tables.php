<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog posts (F2, ARCHITECTURE D55): twins of the lesson tables. Additive only: the previous
 * release never reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('summary', 300)->default('');
            // A lesson_media uuid (images are shared with lessons).
            $table->uuid('cover')->nullable();
            $table->json('tags');
            $table->unsignedSmallInteger('estimated_minutes')->default(1);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('post_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 40);
            $table->string('heading', 160)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['post_id', 'position']);
        });

        Schema::create('post_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['post_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
        Schema::dropIfExists('post_drafts');
        Schema::dropIfExists('post_sections');
        Schema::dropIfExists('posts');
    }
};
