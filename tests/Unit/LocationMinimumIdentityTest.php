<?php

use App\Rules\LocationMinimumIdentity;

it('accepts a non-empty name', function () {
    expect(LocationMinimumIdentity::isSatisfied(['name' => 'Hal A']))->toBeTrue();
});

it('accepts a full address without name', function () {
    expect(LocationMinimumIdentity::isSatisfied([
        'name' => '',
        'street' => 'Industrieweg',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]))->toBeTrue();
});

it('rejects partial address without name', function () {
    expect(LocationMinimumIdentity::isSatisfied([
        'name' => '',
        'street' => 'Industrieweg',
        'postal_code' => '',
        'city' => 'Gent',
    ]))->toBeFalse();
});
