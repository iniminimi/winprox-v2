<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_passkey_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->string('credential_id');
            $table->text('credential_public_key');
            $table->string('user_handle', 128);
            $table->json('transports')->nullable();
            $table->string('aaguid', 36)->nullable();
            $table->boolean('backup_eligible')->nullable();
            $table->boolean('backup_status')->nullable();
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('credential_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_passkey_credentials');
    }
};
