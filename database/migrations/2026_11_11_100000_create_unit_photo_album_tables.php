<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->boolean('photo_album_enabled')->default(false)->after('allow_unit_measurements');
        });

        Schema::create('unit_album_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->string('path');
            $table->date('taken_on');
            $table->timestamps();

            $table->index(['tenant_id', 'unit_id', 'taken_on']);
            $table->index('created_at');
        });

        Schema::create('unit_album_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('email');
            $table->string('description', 500)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'email']);
            $table->index('tenant_id');
        });

        Schema::create('unit_album_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->json('photo_ids');
            $table->unsignedTinyInteger('photo_count');
            $table->date('album_day');
            $table->string('status', 32);
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'unit_id', 'created_at']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_album_sends');
        Schema::dropIfExists('unit_album_recipients');
        Schema::dropIfExists('unit_album_photos');

        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('photo_album_enabled');
        });
    }
};
