<?php

declare(strict_types=1);

use App\Actions\Locations\BulkCreateUnitsAction;

it('expands a two-digit range with prefix', function () {
    $names = app(BulkCreateUnitsAction::class)->namesFromRanges([
        ['start' => '20', 'count' => 3, 'padding' => '', 'prefix' => 'Kamer ', 'suffix' => ''],
    ]);

    expect($names)->toBe(['Kamer 20', 'Kamer 21', 'Kamer 22']);
});

it('expands a three-digit range with prefix', function () {
    $names = app(BulkCreateUnitsAction::class)->namesFromRanges([
        ['start' => '201', 'count' => 3, 'padding' => '', 'prefix' => 'Kamer ', 'suffix' => ''],
    ]);

    expect($names)->toBe(['Kamer 201', 'Kamer 202', 'Kamer 203']);
});

it('keeps leading zeros from start padding', function () {
    $names = app(BulkCreateUnitsAction::class)->namesFromRanges([
        ['start' => '01', 'count' => 1, 'padding' => '', 'prefix' => '', 'suffix' => ''],
    ]);

    expect($names)->toBe(['01']);
});

it('creates a single room number', function () {
    $names = app(BulkCreateUnitsAction::class)->namesFromRanges([
        ['start' => '501', 'count' => 1, 'padding' => '', 'prefix' => 'Kamer ', 'suffix' => ''],
    ]);

    expect($names)->toBe(['Kamer 501']);
});

it('applies suffix per unit', function () {
    $names = app(BulkCreateUnitsAction::class)->namesFromRanges([
        ['start' => '20', 'count' => 2, 'padding' => '', 'prefix' => '', 'suffix' => 'A'],
    ]);

    expect($names)->toBe(['20A', '21A']);
});

it('combines prefix and start for a single range', function () {
    $names = app(BulkCreateUnitsAction::class)->namesFromRanges([
        ['start' => '20', 'count' => 2, 'padding' => '', 'prefix' => 'Kamer ', 'suffix' => ''],
    ]);

    expect($names)->toBe(['Kamer 20', 'Kamer 21']);
});
