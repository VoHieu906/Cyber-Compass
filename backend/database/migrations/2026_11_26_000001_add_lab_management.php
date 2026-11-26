<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab management (F7, D73 to D79). Expand-only: every new column has a default or is nullable,
 * so the previous release keeps working while this one migrates, and existing labs stay live.
 *
 * `solutions` holds the admin-only reference solutions and decoys (a column of its own, so no
 * engine's publicScenario() can leak it). `scenario_version` goes up only when a publish changes
 * what an attempt's state was built from; attempts pin the version they started on (D75).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('provider');
            $table->foreignId('updated_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('scenario_version')->default(1)->after('updated_by');
            $table->unsignedInteger('lock_version')->default(1)->after('scenario_version');
            $table->json('solutions')->nullable()->after('scenario');

            $table->index(['status', 'is_archived', 'position']);
        });

        Schema::table('lab_attempts', function (Blueprint $table) {
            $table->unsignedInteger('scenario_version')->default(1)->after('state_version');
            $table->timestamp('restarted_at')->nullable()->after('completed_at');
        });

        Schema::create('lab_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lab_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lab_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_revisions');
        Schema::dropIfExists('lab_drafts');
        Schema::table('lab_attempts', function (Blueprint $table) {
            $table->dropColumn(['scenario_version', 'restarted_at']);
        });
        Schema::table('labs', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_archived', 'position']);
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['status', 'scenario_version', 'lock_version', 'solutions']);
        });
    }
};
