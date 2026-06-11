<?php

use App\Enums\TaskStatus;
use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('toont het dashboard met tenant-gescopete KPI-tellingen', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->count(3)->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'description' => 'Recente zichtbare melding',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.kpi.locations'))
        ->assertSee(__('dashboard.kpi.units'))
        ->assertSee(__('dashboard.recent.title'))
        ->assertSee('Recente zichtbare melding');
});

it('toont meldingen van een andere tenant niet op het dashboard', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);

    Issue::factory()->create([
        'tenant_id' => $tenantB->id,
        'approved_at' => now(),
        'description' => 'Melding van een andere tenant',
    ]);

    Tenancy::actAs($tenantA->id);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee('Melding van een andere tenant');
});

it('toont de proefperiode-batterijcapsule op het dashboard', function () {
    config(['billing.trial_days' => 30]);

    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(18),
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.trial_capsule.trial_short', ['days' => 18]))
        ->assertSee('wp-dashboard-trial-capsule', false);
});

it('toont de abonnements-batterijcapsule na planactivatie', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now(),
        'billing_plan' => 'starter',
        'billing_active_until' => now()->addDays(29),
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.trial_capsule.paid_short', ['days' => 29]))
        ->assertSee('wp-dashboard-trial-capsule', false);
});
