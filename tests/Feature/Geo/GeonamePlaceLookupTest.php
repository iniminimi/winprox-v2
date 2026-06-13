<?php

declare(strict_types=1);

use App\Actions\Geo\ImportGeonamePlacesAction;
use App\Actions\Geo\ResolveNearestGeonamePlaceAction;
use App\Models\GeonamePlace;

it('imports europe and north america rows from a geonames extract', function () {
    $path = base_path('tests/all_countries_small.txt');

    $result = app(ImportGeonamePlacesAction::class)->handle($path, truncate: true);

    expect($result['imported'])->toBe(10)
        ->and($result['skipped'])->toBe(0)
        ->and(GeonamePlace::query()->count())->toBe(10);

    expect(GeonamePlace::query()->where('country_code', 'AD')->count())->toBe(10);
});

it('resolves a lake name before a nearby mountain within priority radius', function () {
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

    expect($resolved->locationName)->toBe('Estany de les Abelletes')
        ->and($resolved->countryCode)->toBe('AD');
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
