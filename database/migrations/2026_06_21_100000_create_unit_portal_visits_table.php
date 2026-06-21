<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_portal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->index(['tenant_id', 'visited_at']);
            $table->index(['unit_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_portal_visits');
    }
};
