<?php

declare(strict_types=1);

use App\Actions\Geo\ImportGeonamePlacesAction;
use App\Actions\Geo\ResolveNearestGeonamePlaceAction;
use App\Models\GeonamePlace;

it('imports europe and north america rows from a geonames extract', function () {
    $path = storage_path('framework/testing/geonames-import-sample.txt');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    file_put_contents($path, implode("\n", [
        "3038813\tEstany de les Abelletes\tEstany de les Abelletes\t\t42.52915\t1.73362\tH\tLK\tAD\t\t\t\t\t\t0\t\t2260\tEurope/Andorra\t2014-11-05",
        "3038816\tXixerella\tXixerella\t\t42.55327\t1.48736\tP\tPPL\tAD\t\t\t\t\t\t0\t\t1417\tEurope/Andorra\t2009-04-24",
        "9990001\tRoute One\tRoute One\t\t40.0\t-74.0\tR\tRD\tUS\t\t\t\t\t\t0\t\t0\tAmerica/New_York\t2020-01-01",
        "9990002\tTokyo Tower\tTokyo Tower\t\t35.6586\t139.7454\tS\tTOW\tJP\t\t\t\t\t\t0\t\t0\tAsia/Tokyo\t2020-01-01",
    ]));

    $result = app(ImportGeonamePlacesAction::class)->handle($path, truncate: true);

    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(2)
        ->and(GeonamePlace::query()->count())->toBe(2)
        ->and(GeonamePlace::query()->where('country_code', 'AD')->count())->toBe(2);
});

it('prefers land features over a nearby lake', function () {
    GeonamePlace::query()->insert([
        [
            'id' => 3017833,
            'name' => 'Estany de les Abelletes',
            'latitude' => 42.52915,
            'longitude' => 1.73362,
            'country_code' => 'AD',
            'feature_class' => 'H',
            'feature_code' => 'LK',
        ],
        [
            'id' => 3017832,
            'name' => 'Pic de les Abelletes',
            'latitude' => 42.52535,
            'longitude' => 1.73343,
            'country_code' => 'AD',
            'feature_class' => 'T',
            'feature_code' => 'PK',
        ],
    ]);

    $resolved = app(ResolveNearestGeonamePlaceAction::class)->handle(42.5291, 1.7336);

    expect($resolved->locationName)->toBe('Pic de les Abelletes')
        ->and($resolved->countryCode)->toBe('AD');
});

it('uses a lake name only when no better land feature exists nearby', function () {
    GeonamePlace::query()->create([
        'id' => 3017833,
        'name' => 'Estany de les Abelletes',
        'latitude' => 42.52915,
        'longitude' => 1.73362,
        'country_code' => 'AD',
        'feature_class' => 'H',
        'feature_code' => 'LK',
    ]);

    $resolved = app(ResolveNearestGeonamePlaceAction::class)->handle(42.5291, 1.7336);

    expect($resolved->locationName)->toBe('Estany de les Abelletes')
        ->and($resolved->countryCode)->toBe('AD');
});

it('prefers a nearby populated place over a canal or hotel', function () {
    GeonamePlace::query()->insert([
        [
            'id' => 1001,
            'name' => 'Heist',
            'latitude' => 51.34080,
            'longitude' => 3.25500,
            'country_code' => 'BE',
            'feature_class' => 'P',
            'feature_code' => 'PPL',
        ],
        [
            'id' => 1002,
            'name' => 'Isabellavaart',
            'latitude' => 51.34500,
            'longitude' => 3.26000,
            'country_code' => 'BE',
            'feature_class' => 'H',
            'feature_code' => 'DTCH',
        ],
        [
            'id' => 1003,
            'name' => 'Hotel Monterey',
            'latitude' => 51.33900,
            'longitude' => 3.24800,
            'country_code' => 'BE',
            'feature_class' => 'S',
            'feature_code' => 'HTL',
        ],
    ]);

    $resolved = app(ResolveNearestGeonamePlaceAction::class)->handle(51.3374839, 3.2501345);

    expect($resolved->locationName)->toBe('Heist')
        ->and($resolved->countryCode)->toBe('BE');
});

it('returns null location name for generic open sea features but keeps country code', function () {
    GeonamePlace::query()->create([
        'id' => 9000001,
        'name' => 'North Sea',
        'latitude' => 56.0,
        'longitude' => 3.0,
        'country_code' => 'GB',
        'feature_class' => 'H',
        'feature_code' => 'SEA',
    ]);

    $resolved = app(ResolveNearestGeonamePlaceAction::class)->handle(56.0001, 3.0001);

    expect($resolved->locationName)->toBeNull()
        ->and($resolved->countryCode)->toBe('GB');
});

it('returns nulls when the geoname reference table is empty', function () {
    $resolved = app(ResolveNearestGeonamePlaceAction::class)->handle(51.0, 4.0);

    expect($resolved->locationName)->toBeNull()
        ->and($resolved->countryCode)->toBeNull();
});
