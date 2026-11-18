<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bluetooth-klokmodus is teruggetrokken. Kolom mag weg als die al bestond
 * na de eerdere (nu verwijderde) add-migratie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clock_points', 'display_clock_mode')) {
            return;
        }

        Schema::table('clock_points', function (Blueprint $table): void {
            $table->dropColumn('display_clock_mode');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clock_points', 'display_clock_mode')) {
            return;
        }

        Schema::table('clock_points', function (Blueprint $table): void {
            $table->string('display_clock_mode', 16)->default('online')->after('album_days');
        });
    }
};
