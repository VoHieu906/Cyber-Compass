<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6 (D70): content package uploads and exports. Each row belongs to one admin; its file lives on
 * the transfers disk (S3 in production, so the api and the worker read the same file) and expires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);   // import, export
            $table->string('status', 20);      // uploading, validating, ready, applying, applied, exporting, failed
            $table->string('file_name', 200);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('path', 300)->nullable();
            $table->string('upload_id', 1024)->nullable(); // S3 multipart upload
            $table->unsignedSmallInteger('parts')->nullable();
            $table->json('subject')->nullable();  // exports: what is exported
            $table->json('report')->nullable();   // the preview, then the result
            $table->string('error', 1000)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_transfers');
    }
};
