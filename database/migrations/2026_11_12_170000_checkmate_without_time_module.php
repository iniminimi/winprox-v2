<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->where('checkmate_mode', true)
            ->update([
                'has_time_module' => false,
                'time_roster_enabled' => false,
            ]);
    }

    public function down(): void
    {
        DB::table('tenants')
            ->where('checkmate_mode', true)
            ->update([
                'has_time_module' => true,
                'time_roster_enabled' => true,
            ]);
    }
};
