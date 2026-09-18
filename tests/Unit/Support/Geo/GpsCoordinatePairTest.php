<?php

use App\Support\Geo\GpsCoordinatePair;

it('leest Google Maps lat, lng', function () {
    expect(GpsCoordinatePair::tryParse('51.33616702303759, 3.2363064972080355'))
        ->toBe(['51.33616702303759', '3.2363064972080355']);
});

it('aanvaardt spaties rond de komma', function () {
    expect(GpsCoordinatePair::tryParse('  51.05,3.73  '))
        ->toBe(['51.05', '3.73']);
});

it('weigert ongeldige of onvolledige tekst', function (string $text) {
    expect(GpsCoordinatePair::tryParse($text))->toBeNull();
})->with([
    'alleen lat' => '51.336167',
    'adres' => 'Kerkstraat 1 Brugge',
    'komma als decimaal' => '51,336167, 3,236306',
    'buiten bereik' => '91.0, 3.2',
    'leeg' => '',
]);

it('formatteert bestaande coords voor het plakveld', function () {
    expect(GpsCoordinatePair::format('51.05', '3.73'))->toBe('51.05, 3.73')
        ->and(GpsCoordinatePair::format('', '3.73'))->toBe('');
});
