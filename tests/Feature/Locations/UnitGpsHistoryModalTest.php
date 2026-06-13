<?php

use App\Livewire\Locations\UnitGpsHistoryModal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitGpsReport;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('opens gps history modal with reports newest first', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'name' => 'Hoogtewerker 1',
    ]);

    UnitGpsReport::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'latitude' => 50.1,
        'longitude' => 3.1,
        'reported_at' => '2026-06-01 10:00:00',
    ]);
    UnitGpsReport::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'latitude' => 51.2,
        'longitude' => 4.2,
        'reported_at' => '2026-06-13 15:00:00',
    ]);

    Livewire::test(UnitGpsHistoryModal::class)
        ->dispatch('open-unit-gps-history', unitId: $unit->id)
        ->assertSet('show', true)
        ->assertSee('51.2, 4.2')
        ->assertSee('50.1, 3.1')
        ->assertSee(__('locations.units.gps_history.open_maps'));
});
