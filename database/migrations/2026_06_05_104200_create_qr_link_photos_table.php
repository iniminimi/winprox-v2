<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_link_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();

            $table->index(['tenant_id', 'qr_code_id']);
            $table->index(['tenant_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_link_photos');
    }
};
