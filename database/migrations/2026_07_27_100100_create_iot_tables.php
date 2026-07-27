<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 12)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('iot_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('iot_gateway_id')->index()->constrained('iot_gateways')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('sensor_type', 40);
            $table->foreignId('location_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('esg_indicator_id')->nullable()->index()->constrained('esg_indicators')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'iot_gateway_id', 'external_id'], 'iot_sensors_gateway_external_unique');
        });

        Schema::create('iot_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('iot_sensor_id')->index()->constrained('iot_sensors')->cascadeOnDelete();
            $table->string('name');
            $table->string('operator', 10);
            $table->decimal('threshold', 15, 4);
            $table->foreignId('internal_team_id')->nullable()->index()->constrained('internal_teams')->nullOnDelete();
            $table->string('priority', 20)->default('prio_2');
            $table->string('description', 500);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('iot_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('iot_gateway_id')->index()->constrained('iot_gateways')->cascadeOnDelete();
            $table->foreignId('iot_sensor_id')->nullable()->index()->constrained('iot_sensors')->nullOnDelete();
            $table->foreignId('iot_rule_id')->nullable()->index()->constrained('iot_rules')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('external_sensor_id');
            $table->decimal('value', 15, 4)->nullable();
            $table->string('status', 20)->index();
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('issue_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('esg_measurement_id')->nullable()->index()->constrained('esg_measurements')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('received_at')->useCurrent()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'iot_gateway_id', 'idempotency_key'], 'iot_events_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_events');
        Schema::dropIfExists('iot_rules');
        Schema::dropIfExists('iot_sensors');
        Schema::dropIfExists('iot_gateways');
    }
};
