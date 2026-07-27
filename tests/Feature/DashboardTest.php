<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
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

it('sorteert recente meldingen op status en prioriteit', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $makeIssue = function (TaskStatus $status, TaskPriority $priority, string $description) use ($tenant) {
        $issue = Issue::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => $status,
            'approved_at' => now(),
            'description' => $description,
        ]);

        Task::factory()->create([
            'tenant_id' => $tenant->id,
            'issue_id' => $issue->id,
            'status' => $status,
            'priority' => $priority,
        ]);

        return $issue;
    };

    $makeIssue(TaskStatus::Done, TaskPriority::Prio1, 'Afgehandeld prio 1');
    $makeIssue(TaskStatus::InProgress, TaskPriority::Prio2, 'In uitvoering prio 2');
    $makeIssue(TaskStatus::New, TaskPriority::Prio3, 'Nieuw prio 3');
    $makeIssue(TaskStatus::New, TaskPriority::Prio1, 'Nieuw prio 1');

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
            'Nieuw prio 1',
            'Nieuw prio 3',
            'In uitvoering prio 2',
            'Afgehandeld prio 1',
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
