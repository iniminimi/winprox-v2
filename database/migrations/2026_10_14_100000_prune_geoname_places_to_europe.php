<?php

use App\Support\Geo\GeonameEuropeCountryCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('geoname_places')
            ->whereNotIn('country_code', GeonameEuropeCountryCodes::all())
            ->delete();
    }

    public function down(): void
    {
        // Verwijderde rijen zijn niet te herstellen; opnieuw importeren via geonames:import.
    }
};
