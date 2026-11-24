<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sense_points', function (Blueprint $table) {
            $table->string('season_applied', 16)->nullable()->after('voc_high');
        });
    }

    public function down(): void
    {
        Schema::table('sense_points', function (Blueprint $table) {
            $table->dropColumn('season_applied');
        });
    }
};
