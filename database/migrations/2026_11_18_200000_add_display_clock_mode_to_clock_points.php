<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->string('display_clock_mode', 16)->default('online')->after('album_days');
        });
    }

    public function down(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropColumn('display_clock_mode');
        });
    }
};
