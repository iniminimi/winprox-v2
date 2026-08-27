<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('allow_unit_measurements')->default(false)->after('allow_unit_checks');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allow_unit_measurements')->default(false)->after('allow_unit_checks');
        });

        Schema::create('unit_measure_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 32);
            $table->string('unit_of_measure', 32)->nullable();
            $table->decimal('min_value', 14, 4)->nullable();
            $table->decimal('max_value', 14, 4)->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'name'], 'unit_measure_fields_tenant_name_unique');
        });

        Schema::create('unit_measure_field_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_measure_field_id')->constrained('unit_measure_fields')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'unit_measure_field_id'], 'unit_measure_field_unit_unique');
        });

        Schema::create('unit_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_measure_field_id')->constrained('unit_measure_fields')->restrictOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('value_string', 500)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'recorded_at']);
            $table->index(['unit_id', 'unit_measure_field_id', 'recorded_at'], 'unit_meas_unit_field_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_measurements');
        Schema::dropIfExists('unit_measure_field_unit');
        Schema::dropIfExists('unit_measure_fields');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('allow_unit_measurements');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('allow_unit_measurements');
        });
    }
};
