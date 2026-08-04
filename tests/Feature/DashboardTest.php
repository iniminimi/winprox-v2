<?php

use App\Enums\TaskStatus;
use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\ClockPoint;
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
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

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

it('toont na registratie een succesblok met assistant task video op dashboard', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->withSession(['register_success' => true])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('video/assistant_task_160.mp4', false)
        ->assertSee(__('dashboard.register_success.title'));
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

it('zet wacht-op-controle meldingen bovenaan en daarna de nieuwste', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $makeApproved = function (string $description, \Carbon\CarbonInterface $createdAt) use ($tenant) {
        return Issue::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => TaskStatus::New,
            'approved_at' => now(),
            'description' => $description,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    };

    $makeApproved('Oudere goedgekeurd', now()->subDays(3));
    $makeApproved('Nieuwere goedgekeurd', now()->subDay());

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::New,
        'approved_at' => null,
        'description' => 'Oudere wacht op controle',
        'created_at' => now()->subHours(5),
        'updated_at' => now()->subHours(5),
    ]);
    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::New,
        'approved_at' => null,
        'description' => 'Recente wacht op controle',
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::Closed,
        'approved_at' => now(),
        'description' => 'Gesloten verborgen',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee('Gesloten verborgen')
        ->assertSeeInOrder([
            'Recente wacht op controle',
            'Oudere wacht op controle',
            'Nieuwere goedgekeurd',
            'Oudere goedgekeurd',
        ]);
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
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.trial_capsule.trial_short', ['days' => 18]))
        ->assertSee('wp-dashboard-trial-capsule', false);
});

it('toont de abonnements-batterijcapsule na planactivatie', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now(),
        'billing_plan' => 'facility',
        'billing_active_until' => now()->addDays(29),
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.trial_capsule.paid_short', ['days' => 29]))
        ->assertSee('wp-dashboard-trial-capsule', false);
});

it('toont conditionele actie-KPI’s alleen bij telling groter dan nul', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'has_iot_module' => true,
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.kpi.present_now'))
        ->assertDontSee(__('dashboard.kpi.pending_review'))
        ->assertDontSee(__('dashboard.kpi.iot_alarms'));

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::New,
        'approved_at' => null,
        'description' => 'Wacht op QR-controle',
    ]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'source' => \App\Enums\IssueSource::Iot,
        'description' => 'Open IoT-alarm',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.kpi.pending_review'))
        ->assertSee(__('dashboard.kpi.iot_alarms'))
        ->assertSeeHtml('wp-kpi--pending_review')
        ->assertSeeHtml('wp-kpi--iot_alarms');
});

it('verbergt IoT-KPI zonder IoT-module ook bij open IoT-meldingen', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'has_iot_module' => false,
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'source' => \App\Enums\IssueSource::Iot,
        'description' => 'Verborgen IoT zonder module',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee(__('dashboard.kpi.iot_alarms'));
});
