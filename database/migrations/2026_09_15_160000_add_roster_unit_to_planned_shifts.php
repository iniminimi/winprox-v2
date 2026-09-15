<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('roster_code', 8)->nullable()->after('name');
            $table->unique(['location_id', 'roster_code']);
        });

        Schema::table('planned_shifts', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('kind')->constrained('units')->nullOnDelete();
            $table->string('unit_code', 8)->nullable()->after('unit_id');
            $table->string('unit_name')->nullable()->after('unit_code');
            $table->foreignId('location_id')->nullable()->after('unit_name')->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planned_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['unit_name', 'unit_code']);
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['location_id', 'roster_code']);
            $table->dropColumn('roster_code');
        });
    }
};
