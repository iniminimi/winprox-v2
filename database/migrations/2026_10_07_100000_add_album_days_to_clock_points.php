<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            // Bitmask weekdagen waarop het rust-scherm (album/wijzerklok) actief
            // is: bit 0 = ma … bit 6 = zo. Default 31 = ma–vr, weekend uit.
            $table->unsignedTinyInteger('album_days')->default(31)->after('album_mode');
        });
    }

    public function down(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropColumn('album_days');
        });
    }
};
