<?php

declare(strict_types=1);

use App\Actions\Locations\CreateUnitAction;
use App\Actions\Platform\AssignCorporateSubscriptionAction;
use App\Actions\Platform\SetBillingUnitsCapAction;
use App\Livewire\Platform\Tenants;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('past corporate units- en documentlimiet toe via billing_units_cap', function (): void {
    $tenant = Tenant::factory()->create([
        'billing_plan' => 'corporate',
        'billing_active_until' => now()->addMonth(),
        'billing_units_cap' => 5,
        'trial_ends_at' => now()->subDay(),
    ]);
    $location = Location::factory()->for($tenant)->create();
    Unit::factory()->count(5)->for($location)->for($tenant)->create();

    expect($tenant->maxUnitsLimit())->toBe(5)
        ->and($tenant->maxDocumentsOrgLimit())->toBe(5)
        ->and($tenant->isAtUnitLimit())->toBeTrue();

    $user = User::factory()->for($tenant)->create();
    $this->actingAs($user);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('unit_limit_exceeded');

    app(CreateUnitAction::class)->handle($location, [
        'name' => 'Unit 6',
        'type' => 'other',
    ], $tenant->id);
});

it('laat superuser corporate activeren met units-cap', function (): void {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->assertSee(__('platform.corporate_units_cap_hint'))
        ->set('unitsCapInputs.'.$tenant->id, '1500')
        ->call('assignCorporate', $tenant->id)
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('corporate')
        ->and($tenant->billing_units_cap)->toBe(1500)
        ->and($tenant->hasApiAccess())->toBeTrue()
        ->and($tenant->maxUnitsLimit())->toBe(1500);
});

it('werkt units-cap bij voor bestaande corporate tenant', function (): void {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create([
        'billing_plan' => 'corporate',
        'billing_active_until' => now()->addMonth(),
        'billing_units_cap' => 1000,
        'trial_ends_at' => now()->subDay(),
    ]);

    app(SetBillingUnitsCapAction::class)->handle($tenant, 2000, $super->id);

    expect($tenant->fresh()->billing_units_cap)->toBe(2000)
        ->and($tenant->fresh()->maxUnitsLimit())->toBe(2000);
});

it('weigert units-cap buiten corporate', function (): void {
    $tenant = Tenant::factory()->create(['billing_plan' => 'facility_100']);

    app(SetBillingUnitsCapAction::class)->handle($tenant, 500, null);
})->throws(\InvalidArgumentException::class);

it('activeert corporate via action met entitlements', function (): void {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(7)]);

    app(AssignCorporateSubscriptionAction::class)->handle($tenant, 800, $super);

    $fresh = $tenant->fresh();

    expect($fresh->billing_plan)->toBe('corporate')
        ->and($fresh->has_iot_module)->toBeTrue()
        ->and($fresh->has_esg_module)->toBeTrue()
        ->and($fresh->has_time_module)->toBeTrue();
});
