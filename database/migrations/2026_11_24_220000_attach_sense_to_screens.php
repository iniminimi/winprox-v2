<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->boolean('has_sense')->default(false)->after('role');
        });

        Schema::table('sense_points', function (Blueprint $table) {
            $table->foreignId('screen_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
            $table->dropForeign(['unit_id']);
            $table->dropUnique('sense_points_tenant_unit_unique');
        });

        Schema::table('sense_points', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
        });

        $points = DB::table('sense_points')->get();
        foreach ($points as $point) {
            $screenId = DB::table('screens')->where('unit_id', $point->unit_id)->value('id');
            if ($screenId === null) {
                $unit = DB::table('units')->where('id', $point->unit_id)->first();
                if ($unit === null) {
                    continue;
                }
                $now = now();
                $screenId = DB::table('screens')->insertGetId([
                    'tenant_id' => $unit->tenant_id,
                    'unit_id' => $unit->id,
                    'role' => 'sense',
                    'has_sense' => true,
                    'qr_window_seconds' => 3600,
                    'display_sound_enabled' => true,
                    'album_mode' => 'none',
                    'album_days' => 31,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('screens')->where('id', $screenId)->update(['has_sense' => true]);
            }

            DB::table('sense_points')->where('id', $point->id)->update(['screen_id' => $screenId]);
        }
    }

    public function down(): void
    {
        Schema::table('sense_points', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropConstrainedForeignId('screen_id');
        });

        Schema::table('sense_points', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->unique(['tenant_id', 'unit_id'], 'sense_points_tenant_unit_unique');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn('has_sense');
        });
    }
};
