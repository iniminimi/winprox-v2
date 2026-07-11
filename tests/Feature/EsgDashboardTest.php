<?php

declare(strict_types=1);

use App\Actions\Esg\BuildEsgDashboardAction;
use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\TaskStatus;
use App\Livewire\Esg\Dashboard;
use App\Models\EsgIndicator;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('weigert esg-dashboard zonder esg-module', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.dashboard'))
        ->assertForbidden();
});

it('weigert esg-dashboard voor niet-admins', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.dashboard'))
        ->assertForbidden();
});

it('toont setup-stappen bij leeg esg-dashboard', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('esg.dashboard.empty'))
        ->assertSee(__('esg.setup.title'));
});

it('toont kpi-waarden en drempelalarmen op het dashboard', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Blok B']);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Gasmeter']);
    $indicator = EsgIndicator::factory()->numeric('m3')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
        'thresholds' => ['min' => 0, 'max' => 100],
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'status' => TaskStatus::New,
    ]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $task->id,
            esgIndicatorId: $indicator->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $tenant->id,
    );

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Gas m3')
        ->assertSee('150')
        ->assertSee(__('esg.dashboard.kpi.alarms'))
        ->assertSee(__('esg.dashboard.recent.title'))
        ->assertSee(__('esg.dashboard.alarms.title'))
        ->assertSee(__('esg.measurements.outside_thresholds'))
        ->assertSee(__('esg.dashboard.open_tasks.title'));
});

it('toont openstaande esg-taken op het dashboard', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->numeric()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Elektriciteit kWh',
    ]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Gebouw A']);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Meterkast']);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
        'approved_at' => now(),
    ]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'status' => TaskStatus::InProgress,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Elektriciteit kWh')
        ->assertSee('Gebouw A')
        ->assertSee('Meterkast');
});

it('bouwt dashboarddata per tenant via action', function () {
    $tenantA = Tenant::factory()->create(['has_esg_module' => true]);
    $tenantB = Tenant::factory()->create(['has_esg_module' => true]);
    $indicatorA = EsgIndicator::factory()->numeric()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Tenant A indicator',
    ]);
    EsgIndicator::factory()->numeric()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Tenant B indicator',
    ]);
    $location = Location::factory()->create(['tenant_id' => $tenantA->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenantA->id, 'location_id' => $location->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenantA->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicatorA->id,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create(['tenant_id' => $tenantA->id, 'issue_id' => $issue->id]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $task->id,
            esgIndicatorId: $indicatorA->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 42.0,
        ),
        $tenantA->id,
    );

    $dashboard = app(BuildEsgDashboardAction::class)->handle($tenantA->id);

    expect($dashboard->measurementCount)->toBe(1)
        ->and($dashboard->indicatorKpis)->not->toBeEmpty()
        ->and($dashboard->indicatorKpis[0]['name'])->toBe('Tenant A indicator')
        ->and($dashboard->indicatorKpis[0]['value'])->toContain('42');
});

it('toont dashboard-tab in esg-subnav', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.dashboard'))
        ->assertSee(__('esg.nav.dashboard'))
        ->assertSee(__('esg.nav.indicators'))
        ->assertSee(__('esg.nav.measurements'));
});
