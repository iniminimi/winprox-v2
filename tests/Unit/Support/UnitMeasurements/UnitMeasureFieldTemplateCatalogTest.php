<?php

declare(strict_types=1);

use App\Enums\UnitMeasureFieldType;
use App\Support\UnitMeasurements\UnitMeasureFieldTemplateCatalog;

it('lists five measure field templates for the create modal', function () {
    expect(UnitMeasureFieldTemplateCatalog::KEYS)->toHaveCount(5)
        ->and(UnitMeasureFieldTemplateCatalog::menuItems())->toHaveCount(5)
        ->and(collect(UnitMeasureFieldTemplateCatalog::menuItems())->pluck('key')->all())
        ->toBe(UnitMeasureFieldTemplateCatalog::KEYS);
});

it('prefills odometer defaults from the template catalog', function () {
    $defaults = UnitMeasureFieldTemplateCatalog::formDefaults('odometer');

    expect($defaults['name'])->toBe(__('unit_measurements.fields.templates.odometer.name'))
        ->and($defaults['type'])->toBe(UnitMeasureFieldType::Numeric->value)
        ->and($defaults['unitOfMeasure'])->toBe('km')
        ->and($defaults['minValue'])->toBe('0')
        ->and($defaults['maxValue'])->toBeNull();
});

it('prefills status choice options from the template catalog', function () {
    $defaults = UnitMeasureFieldTemplateCatalog::formDefaults('status');

    expect($defaults['type'])->toBe(UnitMeasureFieldType::Choice->value)
        ->and($defaults['choiceOptions'])->toBe([
            __('unit_measurements.fields.templates.status.options.ok'),
            __('unit_measurements.fields.templates.status.options.defect'),
            __('unit_measurements.fields.templates.status.options.maintenance'),
            __('unit_measurements.fields.templates.status.options.out_of_service'),
        ]);
});

it('rejects unknown measure field template keys', function () {
    UnitMeasureFieldTemplateCatalog::formDefaults('unknown');
})->throws(InvalidArgumentException::class);
