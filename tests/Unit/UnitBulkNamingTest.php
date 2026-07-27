<?php

declare(strict_types=1);

use App\Support\Units\UnitBulkNaming;

it('genereert opeenvolgende unitnamen vanaf een startnummer', function () {
    expect(UnitBulkNaming::generate(21, 3, UnitBulkNaming::SCHEME_SEQUENTIAL, 'Kamer'))
        ->toBe(['Kamer 21', 'Kamer 22', 'Kamer 23']);
});

it('genereert opeenvolgende driecijferige reeksen zonder padding', function () {
    expect(UnitBulkNaming::generate(201, 3, UnitBulkNaming::SCHEME_SEQUENTIAL, 'Kamer'))
        ->toBe(['Kamer 201', 'Kamer 202', 'Kamer 203']);
});

it('respecteert prefix met trailing separator bij sequential', function () {
    expect(UnitBulkNaming::generate(1, 2, UnitBulkNaming::SCHEME_SEQUENTIAL, 'Kamer-'))
        ->toBe(['Kamer-1', 'Kamer-2']);
});

it('weigert sequential met te groot aantal', function () {
    expect(UnitBulkNaming::validateConfig(1, UnitBulkNaming::MAX_SEQUENTIAL + 1, UnitBulkNaming::SCHEME_SEQUENTIAL))
        ->toBe('too_many');
});
