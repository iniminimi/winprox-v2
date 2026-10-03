<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            // Rust-scherm tijdens de album-vensters: photos (slideshow),
            // clock (wijzerklok) of none (gewone QR-klok blijft staan).
            $table->string('album_mode', 16)->default('photos')->after('album2_until');
        });
    }

    public function down(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropColumn('album_mode');
        });
    }
};
