<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sense_points', function (Blueprint $table) {
            $table->string('display_locale', 5)->default('nl')->after('season_applied');
        });
    }

    public function down(): void
    {
        Schema::table('sense_points', function (Blueprint $table) {
            $table->dropColumn('display_locale');
        });
    }
};
