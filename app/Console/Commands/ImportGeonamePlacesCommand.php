<?php

namespace App\Console\Commands;

use App\Actions\Geo\ImportGeonamePlacesAction;
use Illuminate\Console\Command;

class ImportGeonamePlacesCommand extends Command
{
    protected $signature = 'geonames:import
                            {path : Pad naar allCountries.txt}
                            {--truncate : Leeg de tabel vóór import}';

    protected $description = 'Importeer GeoNames-plaatsen (Europa + Noord-Amerika) uit allCountries.txt';

    public function handle(ImportGeonamePlacesAction $importGeonamePlaces): int
    {
        $path = (string) $this->argument('path');

        $this->info("GeoNames-import gestart: {$path}");

        $result = $importGeonamePlaces->handle($path, (bool) $this->option('truncate'));

        $this->info(sprintf(
            'Klaar: %d rijen geïmporteerd, %d regels overgeslagen%s.',
            $result['imported'],
            $result['skipped'],
            $result['truncated'] ? ' (tabel geleegd)' : '',
        ));

        return self::SUCCESS;
    }
}
