<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('internal_team_id')->constrained('internal_teams')->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->cascadeOnDelete();
            $table->string('title');
            $table->string('description', 500);
            $table->string('original_language', 5)->default('nl');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'internal_team_id']);
        });

        Schema::create('team_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('internal_team_id')->constrained('internal_teams')->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->cascadeOnDelete();
            $table->string('description', 500);
            $table->string('original_language', 5)->default('nl');
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'internal_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_announcements');
        Schema::dropIfExists('team_documents');
    }
};
