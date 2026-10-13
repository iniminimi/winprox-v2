<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_holiday_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('effect', 16);
            $table->date('replacement_date')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'holiday_date']);
            $table->index(['tenant_id', 'replacement_date']);
            $table->foreign('holiday_date')->references('date')->on('belgian_public_holidays');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_holiday_agreements');
    }
};
