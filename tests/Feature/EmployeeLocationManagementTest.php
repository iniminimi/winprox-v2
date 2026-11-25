<?php

use App\Livewire\Locations\Index as LocationIndex;
use App\Livewire\Pages\Settings;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('laat een beheerder het vinkje zetten en een medewerker daarna locaties beheren', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    expect(Gate::forUser($employee)->allows('create', Location::class))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('manage', $location))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('deactivate', $location))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('update', $location))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', Location::class))->toBeTrue();

    Livewire::actingAs($employee)
        ->test(LocationIndex::class)
        ->assertDontSee(__('locations.add'))
        ->call('openCreate')
        ->assertForbidden();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee('wire:model.live="employeesMayManageLocations"', false)
        ->set('employeesMayManageLocations', true);

    expect($tenant->fresh()->employeesMayManageLocations())->toBeTrue();

    $employee->unsetRelation('tenant');

    Livewire::actingAs($employee)
        ->test(LocationIndex::class)
        ->assertSee(__('locations.add'))
        ->call('openCreate')
        ->assertSet('showModal', true);

    expect(Gate::forUser($employee)->allows('manage', $location))->toBeTrue();
});

it('weigert het vinkje voor een medewerker', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->assertDontSee('wire:model.live="employeesMayManageLocations"', false)
        ->set('employeesMayManageLocations', true)
        ->assertForbidden();

    expect($tenant->fresh()->employeesMayManageLocations())->toBeFalse();
});
