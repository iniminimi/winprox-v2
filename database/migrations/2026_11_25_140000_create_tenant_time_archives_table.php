<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_time_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('period_key', 16);
            $table->string('path');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamp('available_until');
            $table->timestamps();

            $table->unique(['tenant_id', 'kind', 'period_key']);
            $table->index('available_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_time_archives');
    }
};
