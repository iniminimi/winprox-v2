<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DELETE geeft InnoDB-ruimte niet terug aan het OS; een rebuild wel.
        // OPTIMIZE TABLE bestaat niet op SQLite (tests) — alleen op MySQL/MariaDB.
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('OPTIMIZE TABLE geoname_places');
        }
    }

    public function down(): void
    {
        // Niets terug te zetten: dit is pure opslagruimte-reorganisatie.
    }
};
