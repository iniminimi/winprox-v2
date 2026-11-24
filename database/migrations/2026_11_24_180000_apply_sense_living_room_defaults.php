<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sense_points')
            ->where('temperature_low', 18)
            ->where('temperature_target', 21)
            ->where('temperature_high', 24)
            ->where('humidity_low', 40)
            ->where('humidity_target', 50)
            ->where('humidity_high', 60)
            ->where('co2_high', 1200)
            ->where('voc_high', 200)
            ->update([
                'temperature_low' => 20,
                'co2_high' => 900,
                'voc_high' => 150,
            ]);
    }

    public function down(): void
    {
        DB::table('sense_points')
            ->where('temperature_low', 20)
            ->where('temperature_target', 21)
            ->where('temperature_high', 24)
            ->where('humidity_low', 40)
            ->where('humidity_target', 50)
            ->where('humidity_high', 60)
            ->where('co2_high', 900)
            ->where('voc_high', 150)
            ->update([
                'temperature_low' => 18,
                'co2_high' => 1200,
                'voc_high' => 200,
            ]);
    }
};
