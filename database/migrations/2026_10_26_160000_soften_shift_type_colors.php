<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SoftenShiftTypeColors extends Migration
{
    public function up(): void
    {
        foreach (['orange', 'yellow'] as $from) {
            DB::table('shift_types')->where('color', $from)->update(['color' => 'amber']);
        }
    }

    public function down(): void
    {
        DB::table('shift_types')->where('color', 'amber')->update(['color' => 'orange']);
    }
}

return new SoftenShiftTypeColors;
