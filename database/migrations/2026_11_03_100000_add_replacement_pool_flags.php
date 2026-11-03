<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_types', function (Blueprint $table): void {
            $table->boolean('is_replacement_pool')->default(false)->after('is_active');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('time_replacement_include_unplanned')->default(false)->after('time_roster_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('shift_types', function (Blueprint $table): void {
            $table->dropColumn('is_replacement_pool');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('time_replacement_include_unplanned');
        });
    }
};
