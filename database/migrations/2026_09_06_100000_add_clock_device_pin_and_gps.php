<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('time_require_worker_pin')->default(false)->after('has_time_module');
            $table->boolean('time_gps_on_clock')->default(false)->after('time_require_worker_pin');
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->string('clock_pin_hash')->nullable()->after('field_icon_locked_at');
            $table->foreignId('clock_device_id')->nullable()->after('clock_pin_hash')
                ->constrained('worker_devices')->nullOnDelete();
        });

        Schema::table('work_shifts', function (Blueprint $table) {
            $table->decimal('clock_in_latitude', 10, 8)->nullable()->after('clock_in_device_id');
            $table->decimal('clock_in_longitude', 11, 8)->nullable()->after('clock_in_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn(['clock_in_latitude', 'clock_in_longitude']);
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clock_device_id');
            $table->dropColumn('clock_pin_hash');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['time_require_worker_pin', 'time_gps_on_clock']);
        });
    }
};
