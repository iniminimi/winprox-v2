<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->foreignId('presence_clock_point_id')
                ->nullable()
                ->after('clock_in_clock_point_id')
                ->constrained('clock_points')
                ->nullOnDelete();
            $table->json('location_hops')->nullable()->after('total_break_minutes');
        });

        // Bestaande open/gesloten shifts: aanwezigheid = inklokpunt.
        DB::table('work_shifts')
            ->whereNull('presence_clock_point_id')
            ->update([
                'presence_clock_point_id' => DB::raw('clock_in_clock_point_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('presence_clock_point_id');
            $table->dropColumn('location_hops');
        });
    }
};
