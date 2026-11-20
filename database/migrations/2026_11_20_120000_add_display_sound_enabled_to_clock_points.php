<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clock_points', 'display_sound_enabled')) {
            Schema::table('clock_points', function (Blueprint $table) {
                // Geluid van het TFT-klokscherm (piep bij toets en prikking).
                $table->boolean('display_sound_enabled')->default(true)->after('display_on_until');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clock_points', 'display_sound_enabled')) {
            Schema::table('clock_points', function (Blueprint $table) {
                $table->dropColumn('display_sound_enabled');
            });
        }
    }
};
