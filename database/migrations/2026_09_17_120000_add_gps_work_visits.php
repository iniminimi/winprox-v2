<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('time_gps_visits')->default(false)->after('time_gps_on_clock');
            $table->unsignedSmallInteger('time_gps_visit_radius_meters')->nullable()->after('time_gps_visits');
        });

        Schema::table('units', function (Blueprint $table) {
            // Static work-visit pin. Never written by unit_gps_reports.
            $table->decimal('latitude', 10, 8)->nullable()->after('description');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        Schema::create('work_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('start_latitude', 10, 8);
            $table->decimal('start_longitude', 11, 8);
            $table->decimal('end_latitude', 10, 8)->nullable();
            $table->decimal('end_longitude', 11, 8)->nullable();
            $table->string('clock_source', 32);
            $table->timestamps();

            $table->index(['worker_id', 'ended_at']);
            $table->index(['work_shift_id', 'started_at']);
        });

        Schema::table('presence_submissions', function (Blueprint $table) {
            $table->foreignId('work_visit_id')->nullable()->after('work_break_id')->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presence_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_visit_id');
            $table->dropConstrainedForeignId('unit_id');
        });
        Schema::dropIfExists('work_visits');
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['time_gps_visits', 'time_gps_visit_radius_meters']);
        });
    }
};
