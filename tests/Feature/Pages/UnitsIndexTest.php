<?php

use App\Livewire\Pages\UnitsIndex;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('filters tenant units by location in UnitsIndex', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $locationA = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $locationB = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    $unitA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationA->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationB->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);

    Livewire::actingAs($user)
        ->test(UnitsIndex::class)
        ->set('locationFilter', $locationA->id)
        ->assertSee($unitA->name)
        ->assertDontSee($unitB->name);
});

