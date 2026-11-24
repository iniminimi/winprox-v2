<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->boolean('forwarding_enabled')->default(false);
            $table->string('pairing_code', 8)->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->string('device_hint', 64)->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('token_hash', 64)->nullable();
            $table->string('token_prefix', 16)->nullable();
            $table->timestamps();

            $table->unique('unit_id');
            $table->index('pairing_code');
            $table->index('token_hash');
        });

        Schema::create('camera_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->string('claim_token', 40)->unique();
            $table->string('pairing_code', 8);
            $table->string('device_hint', 64);
            $table->string('ip', 45)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('denied_reason', 32)->nullable();
            $table->text('issued_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('pending_slot')->nullable();
            $table->timestamps();

            $table->unique('pending_slot');
            $table->index(['camera_id', 'status']);
        });

        Schema::create('camera_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 64);
            $table->timestamps();

            $table->index(['camera_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_photos');
        Schema::dropIfExists('camera_claims');
        Schema::dropIfExists('cameras');
    }
};
