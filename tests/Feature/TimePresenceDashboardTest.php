<?php

declare(strict_types=1);

use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Actions\Time\ClockInAction;
use App\Enums\TimePresenceAttentionType;
use App\Enums\TimePresenceStatusFilter;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;

it('bouwt een team-dashboard met kpis en aandacht', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Team A']);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Team B']);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $workerActive = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $teamA->id]);
    $workerBreak = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $teamA->id]);
    $workerAbsent = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $teamB->id]);

    app(ClockInAction::class)->handle($workerActive, $clockPoint);
    app(ClockInAction::class)->handle($workerBreak, $clockPoint);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);

    expect($dashboard->kpis->clockedIn)->toBe(2)
        ->and($dashboard->kpis->active)->toBe(2)
        ->and($dashboard->kpis->notClockedIn)->toBe(1)
        ->and($dashboard->teamBuckets)->toHaveCount(2)
        ->and($dashboard->teamBuckets->firstWhere(fn ($b) => $b->team->id === $teamB->id)?->absentCount)->toBe(1);
});

it('filtert op status actief', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle(
        $tenant->id,
        statusFilter: TimePresenceStatusFilter::Absent,
        expandedTeamIds: [$team->id],
    );

    expect($dashboard->kpis->notClockedIn)->toBe(1)
        ->and($dashboard->teamBuckets)->toHaveCount(1)
        ->and($dashboard->teamBuckets->first()->absentWorkers)->toHaveCount(1);
});

it('detecteert lange shifts als aandacht', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    config(['time.long_shift_hours' => 8]);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subHours(9)]);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);

    expect($dashboard->kpis->attention)->toBe(1)
        ->and($dashboard->attentionItems->first()->type)->toBe(TimePresenceAttentionType::LongShift);
});

it('laadt shift-details alleen voor uitgeklapte teams', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    app(ClockInAction::class)->handle($worker, $clockPoint);

    $collapsed = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    $expanded = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id, expandedTeamIds: [$team->id]);

    expect($collapsed->teamBuckets->first()->activeShifts)->toHaveCount(0)
        ->and($expanded->teamBuckets->first()->activeShifts)->toHaveCount(1);
});

it('groepeert ingeklokte medewerkers per locatie', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    app(ClockInAction::class)->handle($worker, $clockPoint);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);

    expect($dashboard->locationBuckets)->toHaveCount(1)
        ->and($dashboard->locationBuckets->first()->clockedInCount)->toBe(1)
        ->and($dashboard->locationBuckets->first()->location?->id)->toBe($location->id);
});
