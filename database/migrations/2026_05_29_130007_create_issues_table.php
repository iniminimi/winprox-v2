<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact')->nullable();
            $table->text('description');
            $table->string('status', 20)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
