<?php

declare(strict_types=1);

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\ApplyPlanEntitlementsAction;
use App\Actions\Billing\StartTenantTrialAction;
use App\Livewire\Dashboard;
use App\Livewire\Pages\Subscription;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingCatalogViewData;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('zet trial op 50 units met Time-prikklok en maakt een Clock Point', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    app(StartTenantTrialAction::class)->handle($tenant);

    $fresh = $tenant->fresh();

    expect($fresh->maxUnitsLimit())->toBe(50)
        ->and($fresh->hasTimeModule())->toBeTrue()
        ->and($fresh->hasIotModule())->toBeFalse()
        ->and($fresh->hasEsgModule())->toBeFalse()
        ->and(ClockPoint::query()->where('tenant_id', $fresh->id)->count())->toBe(1);
});

it('zet Time aan voor een bestaande proeftenant bij het dashboard', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(10),
        'has_time_module' => false,
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)->test(Dashboard::class);

    expect($tenant->fresh()->hasTimeModule())->toBeTrue();
});

it('activeert WinProx 50 zonder Time', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->set('includeTime.winprox_50', false)
        ->call('activatePlan', 'winprox_50')
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('winprox_50')
        ->and($tenant->hasTimeModule())->toBeFalse()
        ->and($tenant->subscriptionPeriodDays())->toBe(365);
});

it('activeert WinProx 50 met Time-prikklok als plan-variant', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->set('includeTime.winprox_50', true)
        ->call('activatePlan', 'winprox_50')
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('winprox_50_time')
        ->and($tenant->hasTimeModule())->toBeTrue()
        ->and($tenant->hasIotModule())->toBeFalse()
        ->and(BillingCatalogViewData::catalogPlanFor('winprox_50_time'))->toBe('winprox_50');
});

it('weigert self-activate van legacy facility-tiers', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    expect(fn () => app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'facility_100', 'manual'))
        ->toThrow(InvalidArgumentException::class, 'plan_not_self_activate');
});

it('behoudt entitlements van een bestaande facility_100-abonnee', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'billing_plan' => 'facility_100',
        'billing_active_until' => now()->addDays(20),
        'has_time_module' => false,
    ]);
    Tenancy::actAs($tenant->id);

    app(ApplyPlanEntitlementsAction::class)->handle($tenant);

    $fresh = $tenant->fresh();

    expect($fresh->maxUnitsLimit())->toBe(100)
        ->and($fresh->hasTimeModule())->toBeTrue()
        ->and($fresh->hasIotModule())->toBeFalse();
});
