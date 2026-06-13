<?php

declare(strict_types=1);

namespace App\Actions\Geo;

use App\Models\GeonamePlace;
use App\Support\Geo\GeonameEuropeNorthAmericaCountryCodes;
use Illuminate\Support\Facades\DB;

class ImportGeonamePlacesAction
{
    private const CHUNK_SIZE = 1000;

    /** @return array{imported: int, skipped: int, truncated: bool} */
    public function handle(string $path, bool $truncate = false): array
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("GeoNames-bestand is niet leesbaar: {$path}");
        }

        if ($truncate) {
            GeonamePlace::query()->delete();
        }

        $imported = 0;
        $skipped = 0;
        $chunk = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("GeoNames-bestand kon niet geopend worden: {$path}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $row = $this->parseLine($line);

                if ($row === null) {
                    $skipped++;

                    continue;
                }

                $chunk[] = $row;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $imported += $this->insertChunk($chunk);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $imported += $this->insertChunk($chunk);
            }
        } finally {
            fclose($handle);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'truncated' => $truncate,
        ];
    }

    /**
     * @return array{id: int, name: string, latitude: float, longitude: float, country_code: string, feature_class: string, feature_code: string}|null
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $parts = explode("\t", $line);
        if (count($parts) < 9) {
            return null;
        }

        $countryCode = strtoupper($parts[8]);
        if (! GeonameEuropeNorthAmericaCountryCodes::allows($countryCode)) {
            return null;
        }

        $featureClass = $parts[6];
        if ($featureClass === 'R') {
            return null;
        }

        $latitude = (float) $parts[4];
        $longitude = (float) $parts[5];
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        $name = trim($parts[1]);
        if ($name === '') {
            return null;
        }

        return [
            'id' => (int) $parts[0],
            'name' => mb_substr($name, 0, 200),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'country_code' => $countryCode,
            'feature_class' => mb_substr($featureClass, 0, 1),
            'feature_code' => mb_substr($parts[7], 0, 10),
        ];
    }

    /**
     * @param  list<array{id: int, name: string, latitude: float, longitude: float, country_code: string, feature_class: string, feature_code: string}>  $chunk
     */
    private function insertChunk(array $chunk): int
    {
        DB::table('geoname_places')->insertOrIgnore($chunk);

        return count($chunk);
    }
}
