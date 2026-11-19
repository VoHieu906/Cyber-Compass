<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('avatar_path')->nullable();
            $table->string('phone', 32)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('education_level', ['high_school', 'undergraduate', 'graduate', 'other'])->nullable();
            $table->enum('locale', ['en', 'vi'])->default('en');
            $table->enum('theme', ['light', 'dark', 'system'])->default('system');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
