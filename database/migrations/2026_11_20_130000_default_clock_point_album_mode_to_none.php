<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            // Nieuwe clock points starten op "QR-code" (geen rust-scherm).
            // Bestaande rijen houden hun bewaarde keuze.
            $table->string('album_mode', 16)->default('none')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->string('album_mode', 16)->default('photos')->change();
        });
    }
};
