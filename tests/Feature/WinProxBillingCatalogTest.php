<?php

declare(strict_types=1);

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\ApplyPlanEntitlementsAction;
use App\Actions\Billing\StartTenantTrialAction;
use App\Actions\Platform\AssignTenantSubscriptionPlanAction;
use App\Actions\Platform\TogglePresenceComplianceAction;
use App\Livewire\Dashboard;
use App\Livewire\Pages\Subscription;
use App\Livewire\Platform\Tenants;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingCatalogViewData;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('zet trial op 50 licenties en 50 units met Time-prikklok en maakt een Clock Point', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    app(StartTenantTrialAction::class)->handle($tenant);

    $fresh = $tenant->fresh();

    expect($fresh->maxUnitsLimit())->toBe(50)
        ->and($fresh->maxSeatsLimit())->toBe(50)
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

it('weigert tenant self-activate van WinProx-formules', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->call('activatePlan', 'winprox_50')
        ->assertHasErrors(['plan']);

    expect($tenant->fresh()->billing_plan)->toBeNull();
});

it('laat superuser WinProx 50 toewijzen met Time inbegrepen', function () {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);

    Livewire::actingAs($super)
        ->test(Tenants::class)
        ->set('planInputs.'.$tenant->id, 'winprox_50')
        ->call('assignPlan', $tenant->id)
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('winprox_50')
        ->and($tenant->hasTimeModule())->toBeTrue()
        ->and($tenant->subscriptionPeriodDays())->toBe(365)
        ->and($tenant->maxSeatsLimit())->toBe(50)
        ->and($tenant->maxUnitsLimit())->toBe(50);
});

it('houdt Time-plan-variant beschikbaar buiten de catalogus', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'winprox_50_time', 'platform');

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('winprox_50_time')
        ->and($tenant->hasTimeModule())->toBeTrue()
        ->and($tenant->hasIotModule())->toBeFalse()
        ->and(BillingCatalogViewData::catalogPlanFor('winprox_50_time'))->toBe('winprox_50')
        ->and(BillingCatalogViewData::publicPlanKeys())->toBe(['winprox_10', 'winprox_25', 'winprox_50', 'corporate']);
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

it('zet CIAO aan zonder Corporate en schakelt Time mee in', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(10),
        'has_time_module' => false,
        'presence_compliance_enabled' => false,
        'billing_plan' => null,
    ]);
    $super = User::factory()->superuser()->create();

    app(TogglePresenceComplianceAction::class)->handle($tenant, $super->id);

    $fresh = $tenant->fresh();

    expect($fresh->presence_compliance_enabled)->toBeTrue()
        ->and($fresh->hasTimeModule())->toBeTrue()
        ->and($fresh->billing_plan)->toBeNull()
        ->and(Tenant::normalizeBillingPlanKey($fresh->billing_plan))->not->toBe('corporate');
});

it('wijst winprox_10 toe via AssignTenantSubscriptionPlanAction', function () {
    $super = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(3)]);

    app(AssignTenantSubscriptionPlanAction::class)->handle($tenant, 'winprox_10', $super);

    expect($tenant->fresh()->billing_plan)->toBe('winprox_10')
        ->and($tenant->fresh()->hasTimeModule())->toBeTrue()
        ->and($tenant->fresh()->billing_units_cap)->toBeNull();
});
