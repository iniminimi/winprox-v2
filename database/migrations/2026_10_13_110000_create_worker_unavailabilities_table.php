<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->timestamps();

            $table->unique(['worker_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_unavailabilities');
    }
};
