<?php

declare(strict_types=1);

use App\Actions\Locations\CreateUnitAction;
use App\Actions\Platform\AssignCorporateSubscriptionAction;
use App\Actions\Platform\SetBillingUnitsCapAction;
use App\Actions\Team\CreateColleagueAction;
use App\Livewire\Platform\Tenants;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('past het afgesproken corporate-aantal toe als licentielimiet', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create([
        'billing_plan' => 'corporate',
        'billing_active_until' => now()->addMonth(),
        'billing_units_cap' => 5,
        'trial_ends_at' => now()->subDay(),
    ]);
    $location = Location::factory()->for($tenant)->create();
    Unit::factory()->count(5)->for($location)->for($tenant)->create();
    $admin = User::factory()->admin()->for($tenant)->create();
    User::factory()->employee()->count(4)->for($tenant)->create();

    expect($tenant->maxSeatsLimit())->toBe(5)
        ->and($tenant->maxUnitsLimit())->toBeNull()
        ->and($tenant->maxDocumentsOrgLimit())->toBeNull()
        ->and($tenant->maxLocationsLimit())->toBeNull()
        ->and($tenant->maxPhotosOrgLimit())->toBeNull()
        ->and($tenant->isAtUnitLimit())->toBeFalse()
        ->and($tenant->fresh()->currentSeatsCount())->toBe(5)
        ->and($tenant->canAddSeat())->toBeFalse();

    $this->actingAs($admin);

    app(CreateUnitAction::class)->handle($location, [
        'name' => 'Unit 6',
        'type' => 'other',
    ], $tenant->id);

    expect($tenant->fresh()->currentUnitsCount())->toBe(6);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('seat_limit_exceeded');

    app(CreateColleagueAction::class)->handle([
        'name' => 'Zesde',
        'email' => 'zesde@example.test',
        'locale' => 'nl',
        'role' => User::ROLE_ADMIN,
        'password' => 'wachtwoord123',
        'send_account_email' => false,
    ], $tenant->id);
});

it('laat superuser corporate activeren met units-cap', function (): void {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->assertSee(__('platform.plan_assign_hint'))
        ->set('planInputs.'.$tenant->id, 'corporate')
        ->set('unitsCapInputs.'.$tenant->id, '1500')
        ->call('assignPlan', $tenant->id)
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('corporate')
        ->and($tenant->billing_units_cap)->toBe(1500)
        ->and($tenant->hasApiAccess())->toBeTrue()
        ->and($tenant->maxSeatsLimit())->toBe(1500)
        ->and($tenant->maxUnitsLimit())->toBeNull()
        ->and($tenant->maxDocumentsOrgLimit())->toBeNull()
        ->and($tenant->subscriptionPeriodDays())->toBe(365)
        ->and($tenant->billing_active_until?->toDateString())->toBe(now()->addDays(365)->toDateString());
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
        ->and($tenant->fresh()->maxSeatsLimit())->toBe(2000)
        ->and($tenant->fresh()->maxUnitsLimit())->toBeNull();
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
        ->and($fresh->has_time_module)->toBeTrue()
        ->and($fresh->subscriptionPeriodDays())->toBe(365)
        ->and($fresh->billing_active_until?->toDateString())->toBe(now()->addDays(365)->toDateString());
});
