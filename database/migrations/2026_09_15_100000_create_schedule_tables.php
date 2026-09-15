<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('label', 80);
            $table->char('start_time', 5);
            $table->char('end_time', 5);
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('color', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('planned_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_type_id')->nullable()->constrained('shift_types')->nullOnDelete();
            $table->char('start_time', 5);
            $table->char('end_time', 5);
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('status', 16);
            $table->timestamps();

            $table->index(['tenant_id', 'work_date']);
            $table->index(['worker_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_shifts');
        Schema::dropIfExists('shift_types');
    }
};
