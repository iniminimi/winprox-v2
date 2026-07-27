<?php

declare(strict_types=1);

use App\Support\Units\UnitBulkNaming;

it('genereert 2-cijferige kamers vanaf een verdieping', function () {
    expect(UnitBulkNaming::generate(2, 3, UnitBulkNaming::SCHEME_SEQUENTIAL_2, 'Kamer'))
        ->toBe(['Kamer 20', 'Kamer 21', 'Kamer 22']);
});

it('genereert 3-cijferige kamers vanaf een verdieping', function () {
    expect(UnitBulkNaming::generate(2, 3, UnitBulkNaming::SCHEME_SEQUENTIAL_3, 'Kamer'))
        ->toBe(['Kamer 201', 'Kamer 202', 'Kamer 203']);
});

it('respecteert prefix met trailing separator bij sequential', function () {
    expect(UnitBulkNaming::generate(1, 2, UnitBulkNaming::SCHEME_SEQUENTIAL_2, 'Kamer-'))
        ->toBe(['Kamer-10', 'Kamer-11']);
});

it('weigert sequential_2 met meer dan 10 units', function () {
    expect(UnitBulkNaming::validateConfig(2, UnitBulkNaming::MAX_SEQUENTIAL_2 + 1, UnitBulkNaming::SCHEME_SEQUENTIAL_2))
        ->toBe('scheme_rooms');
});

it('weigert sequential_3 met meer dan 99 units', function () {
    expect(UnitBulkNaming::validateConfig(2, UnitBulkNaming::MAX_SEQUENTIAL_3 + 1, UnitBulkNaming::SCHEME_SEQUENTIAL_3))
        ->toBe('scheme_rooms');
});
