<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('work_menu_calendar_enabled')->default(true)->after('has_time_module');
            $table->boolean('work_menu_reservations_enabled')->default(true)->after('work_menu_calendar_enabled');
            $table->boolean('work_menu_inspection_rounds_enabled')->default(true)->after('work_menu_reservations_enabled');
            $table->boolean('work_menu_unit_measurements_enabled')->default(true)->after('work_menu_inspection_rounds_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'work_menu_calendar_enabled',
                'work_menu_reservations_enabled',
                'work_menu_inspection_rounds_enabled',
                'work_menu_unit_measurements_enabled',
            ]);
        });
    }
};
