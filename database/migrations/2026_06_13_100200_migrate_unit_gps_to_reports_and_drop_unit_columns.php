<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('units', 'latitude')) {
            return;
        }

        DB::table('units')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->chunkById(100, function ($units): void {
                foreach ($units as $unit) {
                    DB::table('unit_gps_reports')->insert([
                        'tenant_id' => $unit->tenant_id,
                        'unit_id' => $unit->id,
                        'latitude' => $unit->latitude,
                        'longitude' => $unit->longitude,
                        'reported_at' => $unit->updated_at ?? now(),
                        'worker_id' => null,
                        'created_at' => now(),
                    ]);
                }
            });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('is_active');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        $latestReports = DB::table('unit_gps_reports')
            ->select('unit_id', 'latitude', 'longitude')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('unit_gps_reports')
                    ->groupBy('unit_id');
            })
            ->get();

        foreach ($latestReports as $report) {
            DB::table('units')
                ->where('id', $report->unit_id)
                ->update([
                    'latitude' => $report->latitude,
                    'longitude' => $report->longitude,
                ]);
        }
    }
};
