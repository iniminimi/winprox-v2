<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clock_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('qr_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('internal_team_id')->constrained('internal_teams')->cascadeOnDelete();
            $table->foreignId('clock_in_clock_point_id')->constrained('clock_points')->cascadeOnDelete();
            $table->foreignId('clock_out_clock_point_id')->nullable()->constrained('clock_points')->nullOnDelete();
            $table->string('status', 32);
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_in_client_at')->nullable();
            $table->string('clock_in_source', 32);
            $table->foreignId('clock_in_device_id')->nullable()->constrained('worker_devices')->nullOnDelete();
            $table->timestamp('clock_out_at')->nullable();
            $table->timestamp('clock_out_client_at')->nullable();
            $table->string('clock_out_source', 32)->nullable();
            $table->unsignedInteger('total_break_minutes')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'clock_in_at']);
            $table->index(['worker_id', 'status']);
        });

        Schema::create('work_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('break_type', 32);
            $table->timestamps();

            $table->index(['work_shift_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_breaks');
        Schema::dropIfExists('work_shifts');
        Schema::dropIfExists('clock_points');
    }
};
