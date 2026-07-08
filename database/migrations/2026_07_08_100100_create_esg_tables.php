<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('unit_of_measure')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('thresholds')->nullable();
            $table->timestamps();
        });

        Schema::create('esg_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('esg_indicator_id')->constrained('esg_indicators')->restrictOnDelete();
            $table->foreignId('worker_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->decimal('value_numeric', 15, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('value_string', 500)->nullable();
            $table->json('value_json')->nullable();
            $table->foreignId('corrects_measurement_id')
                ->nullable()
                ->constrained('esg_measurements')
                ->nullOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'esg_indicator_id', 'recorded_at'], 'esg_analytics_indicator');
            $table->index(['tenant_id', 'unit_id', 'recorded_at'], 'esg_analytics_unit');
            $table->index(['tenant_id', 'location_id', 'recorded_at'], 'esg_analytics_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_measurements');
        Schema::dropIfExists('esg_indicators');
    }
};
