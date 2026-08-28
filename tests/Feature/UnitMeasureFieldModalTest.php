<?php

declare(strict_types=1);

use App\Livewire\UnitMeasurements\MeasurementsIndex;
use App\Models\Tenant;
use App\Models\UnitMeasureField;
use App\Models\User;
use Livewire\Livewire;

it('closes the measure-field modal after a successful save', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->call('openCreateModal')
        ->assertSet('showModal', true)
        ->set('name', 'Kilometerstand')
        ->set('type', 'numeric')
        ->set('unitOfMeasure', 'km')
        ->call('save')
        ->assertSet('showModal', false)
        ->assertHasNoErrors();

    expect(UnitMeasureField::query()->where('tenant_id', $tenant->id)->where('name', 'Kilometerstand')->exists())->toBeTrue();
});

it('shows unit measurements page title and expandable measure fields', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSee(__('unit_measurements.list.title'))
        ->assertSee(__('unit_measurements.fields.section_title'))
        ->assertSee(__('unit_measurements.list.results_title'));
});

it('shows the filter panel after a measure field exists', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    UnitMeasureField::factory()->numeric('km')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kilometerstand',
    ]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSee(__('common.list.filters_title'))
        ->assertSee(__('unit_measurements.filter.apply'));
});

it('shows example templates in the create modal and prefills odometer', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->call('openCreateModal')
        ->assertSee(__('unit_measurements.fields.templates.label'))
        ->assertSee(__('unit_measurements.fields.templates.odometer.name'))
        ->assertSee(__('unit_measurements.fields.templates.stock_count.name'))
        ->assertSee('wp-tooltip__bubble', false)
        ->assertSee('role="tooltip"', false)
        ->assertSeeHtml('aria-label="'.e(__('unit_measurements.fields.templates.hint')).'"')
        ->call('applyFieldTemplate', 'odometer')
        ->assertSet('name', __('unit_measurements.fields.templates.odometer.name'))
        ->assertSet('type', 'numeric')
        ->assertSet('unitOfMeasure', 'km')
        ->assertSet('minValue', '0')
        ->assertSet('maxValue', '999999')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    expect(UnitMeasureField::query()->where('tenant_id', $tenant->id)->where('name', __('unit_measurements.fields.templates.odometer.name'))->exists())->toBeTrue();
});

it('prefills the status template with choice options', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->call('openCreateModal')
        ->call('applyFieldTemplate', 'status')
        ->assertSet('type', 'choice')
        ->assertSet('choiceOptions', [
            __('unit_measurements.fields.templates.status.options.ok'),
            __('unit_measurements.fields.templates.status.options.defect'),
            __('unit_measurements.fields.templates.status.options.maintenance'),
            __('unit_measurements.fields.templates.status.options.out_of_service'),
        ]);
});

it('hides example templates when editing an existing measure field', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $field = UnitMeasureField::factory()->numeric('km')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kilometerstand',
    ]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->call('openEditModal', $field->id)
        ->assertDontSee(__('unit_measurements.fields.templates.label'));
});
