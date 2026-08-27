<?php

declare(strict_types=1);

use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use App\Models\Worker;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     location: Location,
 *     team: InternalTeam,
 *     unit: Unit,
 *     field: UnitMeasureField
 * }
 */
function unitMeasurementPortalFixture(array $unitOverrides = []): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_measurements' => true,
    ]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('unit-token')->create(array_merge([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_measurements' => true,
    ], $unitOverrides));

    $field = UnitMeasureField::factory()->numeric('km')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kilometerstand',
    ]);
    $unit->measureFields()->sync([$field->id]);

    return compact('tenant', 'location', 'team', 'unit', 'field');
}

it('shows measure tile when measurements are enabled and fields are linked', function () {
    unitMeasurementPortalFixture();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSee(__('portal.tiles.measure'))
        ->call('openSection', 'measure')
        ->assertSet('portalSection', 'measure')
        ->assertSee('Kilometerstand');
});

it('hides measure tile when flag or fields are missing', function () {
    ['unit' => $unit] = unitMeasurementPortalFixture(['allow_unit_measurements' => false]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertDontSee(__('portal.tiles.measure'))
        ->call('openSection', 'measure')
        ->assertSet('portalSection', 'home');

    $unit->update(['allow_unit_measurements' => true]);
    $unit->measureFields()->sync([]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertDontSee(__('portal.tiles.measure'))
        ->call('openSection', 'measure')
        ->assertSet('portalSection', 'home');
});

it('records portal measurements without a worker', function () {
    ['tenant' => $tenant, 'unit' => $unit, 'field' => $field] = unitMeasurementPortalFixture();

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'measure')
        ->set('measureValues.'.$field->id, '1000')
        ->call('submitMeasurements')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'home')
        ->assertSee(__('portal.measure.recorded'));

    $measurement = UnitMeasurement::query()->first();
    expect($measurement)->not->toBeNull()
        ->and($measurement->tenant_id)->toBe($tenant->id)
        ->and($measurement->unit_id)->toBe($unit->id)
        ->and($measurement->unit_measure_field_id)->toBe($field->id)
        ->and((float) $measurement->value_numeric)->toBe(1000.0)
        ->and($measurement->worker_id)->toBeNull()
        ->and($measurement->source->value)->toBe('portal');
});

it('rejects portal numeric values above the field maximum and keeps the form open', function () {
    ['field' => $field] = unitMeasurementPortalFixture();
    $field->update(['max_value' => 999999]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'measure')
        ->set('measureValues.'.$field->id, '5000000')
        ->call('submitMeasurements')
        ->assertHasErrors(['measureValues.'.$field->id])
        ->assertSet('portalSection', 'measure')
        ->assertSee(__('unit_measurements.errors.value_above_max', ['max' => 999999]));

    expect(UnitMeasurement::query()->count())->toBe(0);
});

it('rejects portal numeric values below the field minimum', function () {
    ['field' => $field] = unitMeasurementPortalFixture();
    $field->update(['min_value' => 10]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'measure')
        ->set('measureValues.'.$field->id, '5')
        ->call('submitMeasurements')
        ->assertHasErrors(['measureValues.'.$field->id])
        ->assertSet('portalSection', 'measure');

    expect(UnitMeasurement::query()->count())->toBe(0);
});

it('attaches verified worker when present', function () {
    ['tenant' => $tenant, 'team' => $team, 'field' => $field] = unitMeasurementPortalFixture();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('openSection', 'measure')
        ->set('measureValues.'.$field->id, '250')
        ->call('submitMeasurements')
        ->assertHasNoErrors();

    expect(UnitMeasurement::query()->first()?->worker_id)->toBe($worker->id);
});
