<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| F3 course map (ARCHITECTURE D57, D58). Two new tables, nothing changed: the previous release
| never reads them. Separate tables, not JSON columns, so the many `SELECT *` lesson queries
| (roadmap, curriculum, search) never carry architectures along.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_graphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('graph');
            $table->unsignedInteger('lock_version')->default(1);
            // Set by an admin save: the database then owns the graph and imports skip it (D58).
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lesson_architectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('architecture');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_architectures');
        Schema::dropIfExists('course_graphs');
    }
};
