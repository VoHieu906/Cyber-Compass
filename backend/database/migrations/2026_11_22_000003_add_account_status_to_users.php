<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Phase 3.5 (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §4, migration 6). Additive: every
| existing account stays active (suspended_at null).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('role');
            $table->string('suspension_reason', 300)->nullable()->after('suspended_at');
            $table->timestamp('last_login_at')->nullable()->after('suspension_reason');
            $table->index(['role', 'suspended_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'suspended_at']);
            $table->dropColumn(['suspended_at', 'suspension_reason', 'last_login_at']);
        });
    }
};
