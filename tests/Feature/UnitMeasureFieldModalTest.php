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
