<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('worker_holiday_agreements');
    }

    public function down(): void
    {
        Schema::create('worker_holiday_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('effect', 16);
            $table->date('replacement_date')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'holiday_date']);
            $table->index(['worker_id', 'replacement_date']);
            $table->foreign('holiday_date')->references('date')->on('belgian_public_holidays');
        });
    }
};
