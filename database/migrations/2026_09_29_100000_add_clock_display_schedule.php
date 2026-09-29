<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clock_points', 'display_on_from')) {
            Schema::table('clock_points', function (Blueprint $table) {
                // Aan-uren van het TFT-klokscherm (bv. crèche 06:00–19:00).
                // Beide NULL = altijd aan; gelijk = altijd aan (firmware-regel).
                $table->time('display_on_from')->nullable()->after('display_last_seen_at');
                $table->time('display_on_until')->nullable()->after('display_on_from');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropColumn(['display_on_from', 'display_on_until']);
        });
    }
};
