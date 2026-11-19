<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bluetooth-prikken zijn uitgeschakeld; oude rijen houden nog source=bluetooth
 * vast, wat de ClockSource-cast liet crashen op het portaal.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['clock_in_source', 'clock_out_source'] as $column) {
            DB::table('work_shifts')
                ->where($column, 'bluetooth')
                ->update([$column => 'clock_point_qr']);
        }

        DB::table('work_visits')
            ->where('clock_source', 'bluetooth')
            ->update(['clock_source' => 'clock_point_qr']);
    }

    public function down(): void
    {
        // Onomkeerbaar: we weten niet welke QR-prikken ooit bluetooth waren.
    }
};
