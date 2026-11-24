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
            $table->text('display_secret')->nullable()->after('display_id');
            $table->unsignedInteger('qr_window_seconds')->default(3600)->after('display_secret');
            $table->time('display_on_from')->nullable()->after('qr_window_seconds');
            $table->time('display_on_until')->nullable()->after('display_on_from');
            $table->boolean('display_sound_enabled')->default(true)->after('display_on_until');
            $table->string('album_mode', 16)->default('none')->after('display_sound_enabled');
            $table->time('album1_from')->nullable()->after('album_mode');
            $table->time('album1_until')->nullable()->after('album1_from');
            $table->time('album2_from')->nullable()->after('album1_until');
            $table->time('album2_until')->nullable()->after('album2_from');
            $table->unsignedTinyInteger('album_days')->default(31)->after('album2_until');
        });

        Schema::table('clock_display_images', function (Blueprint $table) {
            $table->foreignId('screen_id')->nullable()->after('clock_point_id')->constrained()->cascadeOnDelete();
        });

        $clocks = DB::table('screens')->whereNotNull('clock_point_id')->get(['id', 'clock_point_id']);
        foreach ($clocks as $screen) {
            $point = DB::table('clock_points')->where('id', $screen->clock_point_id)->first();
            if ($point === null) {
                continue;
            }

            DB::table('screens')->where('id', $screen->id)->update([
                'display_secret' => $point->display_secret,
                'qr_window_seconds' => 30,
                'display_on_from' => $point->display_on_from,
                'display_on_until' => $point->display_on_until,
                'display_sound_enabled' => $point->display_sound_enabled ?? true,
                'album_mode' => $point->album_mode ?? 'none',
                'album1_from' => $point->album1_from,
                'album1_until' => $point->album1_until,
                'album2_from' => $point->album2_from,
                'album2_until' => $point->album2_until,
                'album_days' => $point->album_days ?? 31,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('clock_display_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('screen_id');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn([
                'display_secret',
                'qr_window_seconds',
                'display_on_from',
                'display_on_until',
                'display_sound_enabled',
                'album_mode',
                'album1_from',
                'album1_until',
                'album2_from',
                'album2_until',
                'album_days',
            ]);
        });
    }
};
