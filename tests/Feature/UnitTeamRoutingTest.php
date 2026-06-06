<?php

use App\Actions\Units\SyncUnitTeamsAction;
use App\Data\Units\SyncUnitTeamsData;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Event;
use App\Events\Units\UnitTeamsSynced;

test('can sync teams to a unit', function () {
    config(['audit.enabled' => false]);

    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::create(['location_id' => $location->id, 'tenant_id' => $tenant->id, 'name' => 'Test Unit']);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $action = new SyncUnitTeamsAction(app(AuditRecorder::class));
    $data = SyncUnitTeamsData::fromRequest(['teams' => [$team1->id, $team2->id]]);

    Event::fake();

    $action->handle($unit, $data, 1);

    expect($unit->teams()->count())->toBe(2);
    expect($unit->teams()->pluck('id')->toArray())->toContain($team1->id);
    expect($unit->teams()->pluck('id')->toArray())->toContain($team2->id);

    Event::assertDispatched(UnitTeamsSynced::class);
});

test('syncing teams replaces existing teams', function () {
    config(['audit.enabled' => false]);

    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::create(['location_id' => $location->id, 'tenant_id' => $tenant->id, 'name' => 'Test Unit']);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team3 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $action = new SyncUnitTeamsAction(app(AuditRecorder::class));

    // Sync team1 and team2
    $action->handle($unit, SyncUnitTeamsData::fromRequest(['teams' => [$team1->id, $team2->id]]), 1);
    expect($unit->teams()->count())->toBe(2);

    // Sync team3 only
    $action->handle($unit, SyncUnitTeamsData::fromRequest(['teams' => [$team3->id]]), 1);
    expect($unit->teams()->count())->toBe(1);
    expect($unit->teams()->first()->id)->toBe($team3->id);
});

test('syncing empty array removes all teams', function () {
    config(['audit.enabled' => false]);

    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::create(['location_id' => $location->id, 'tenant_id' => $tenant->id, 'name' => 'Test Unit']);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $action = new SyncUnitTeamsAction(app(AuditRecorder::class));

    // Sync team1
    $action->handle($unit, SyncUnitTeamsData::fromRequest(['teams' => [$team1->id]]), 1);
    expect($unit->teams()->count())->toBe(1);

    // Sync empty array
    $action->handle($unit, SyncUnitTeamsData::fromRequest(['teams' => []]), 1);
    expect($unit->teams()->count())->toBe(0);
});

test('teams are scoped to tenant', function () {
    config(['audit.enabled' => false]);

    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant1->id]);
    $unit = Unit::create(['location_id' => $location->id, 'tenant_id' => $tenant1->id, 'name' => 'Test Unit']);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant1->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant2->id]);

    $action = new SyncUnitTeamsAction(app(AuditRecorder::class));

    // Try to sync team from different tenant
    $action->handle($unit, SyncUnitTeamsData::fromRequest(['teams' => [$team2->id]]), 1);

    // Team should not be synced (tenant isolation)
    expect($unit->teams()->count())->toBe(0);
});

test('resolve_team_for_task_returns_first_assigned_team', function () {
    config(['audit.enabled' => false]);

    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::create(['location_id' => $location->id, 'tenant_id' => $tenant->id, 'name' => 'Test Unit']);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $action = new SyncUnitTeamsAction(app(AuditRecorder::class));
    $action->handle($unit, SyncUnitTeamsData::fromRequest(['teams' => [$team1->id, $team2->id]]), 1);

    $resolveAction = new \App\Actions\Tasks\ResolveTeamForTaskAction();
    $team = $resolveAction->handle($unit);

    expect($team)->not->toBeNull();
    expect($team->id)->toBeIn([$team1->id, $team2->id]);
});

test('resolve_team_for_task_returns_null_when_no_teams_assigned', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['location_id' => $location->id, 'tenant_id' => $tenant->id]);

    $resolveAction = new \App\Actions\Tasks\ResolveTeamForTaskAction();
    $team = $resolveAction->handle($unit);

    expect($team)->toBeNull();
});
