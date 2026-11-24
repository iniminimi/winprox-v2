<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sense_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 16)->index();
            $table->decimal('temperature_low', 4, 1);
            $table->decimal('temperature_target', 4, 1);
            $table->decimal('temperature_high', 4, 1);
            $table->decimal('humidity_low', 5, 1);
            $table->decimal('humidity_target', 5, 1);
            $table->decimal('humidity_high', 5, 1);
            $table->unsignedSmallInteger('co2_high');
            $table->unsignedSmallInteger('voc_high');
            $table->decimal('last_temperature', 4, 1)->nullable();
            $table->decimal('last_humidity', 5, 1)->nullable();
            $table->unsignedInteger('last_co2')->nullable();
            $table->unsignedSmallInteger('last_voc')->nullable();
            $table->timestamp('last_recorded_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'unit_id'], 'sense_points_tenant_unit_unique');
        });

        Schema::create('sense_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('sense_point_id')->index()->constrained('sense_points')->cascadeOnDelete();
            $table->decimal('temperature', 4, 1);
            $table->decimal('humidity', 5, 1);
            $table->unsignedInteger('co2');
            $table->unsignedSmallInteger('voc');
            $table->timestamp('recorded_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['sense_point_id', 'recorded_at'], 'sense_readings_point_recorded_unique');
            $table->index(['tenant_id', 'recorded_at'], 'sense_readings_tenant_recorded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sense_readings');
        Schema::dropIfExists('sense_points');
    }
};
