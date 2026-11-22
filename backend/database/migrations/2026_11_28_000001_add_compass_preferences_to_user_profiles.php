<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 7.5 (D53): the compass menu switch and the learner's keyboard shortcut overrides. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->boolean('compass_enabled')->default(true)->after('timezone');
            $table->json('shortcuts')->nullable()->after('compass_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn(['compass_enabled', 'shortcuts']));
    }
};
