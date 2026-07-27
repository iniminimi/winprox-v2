<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_purge_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_name');
            $table->string('track', 16);
            $table->string('status', 32);
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('export_acknowledged_at')->nullable();
            $table->timestamp('password_verified_at')->nullable();
            $table->timestamp('email_confirmed_at')->nullable();
            $table->foreignId('email_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confirmation_token_hash', 64)->nullable();
            $table->timestamp('scheduled_purge_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('backup_path')->nullable();
            $table->timestamp('backup_expires_at')->nullable();
            $table->json('deleted_counts')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_purge_at']);
            $table->index(['status', 'backup_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_purge_requests');
    }
};
