<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_sync_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 36);
            $table->string('type', 32);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('status', 16);
            $table->unsignedSmallInteger('http_status');
            $table->json('result')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'worker_id', 'client_id'], 'field_sync_receipts_unique');
            $table->index(['tenant_id', 'worker_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_sync_receipts');
    }
};
