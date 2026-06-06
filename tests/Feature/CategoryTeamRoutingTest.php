<?php

use App\Actions\Categories\SyncCategoryTeamsAction;
use App\Data\Categories\SyncCategoryTeamsData;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('audit.enabled', false);
});

test('can sync teams to a category', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->for($tenant)->create();
    $category = Category::factory()->for($tenant)->create();
    $team1 = InternalTeam::factory()->for($tenant)->create();
    $team2 = InternalTeam::factory()->for($tenant)->create();
    $user = User::factory()->for($tenant)->create();

    $action = app(SyncCategoryTeamsAction::class);
    $action->handle(
        $category,
        SyncCategoryTeamsData::fromRequest(['teams' => [$team1->id, $team2->id]]),
        $user,
    );

    expect($category->teams()->pluck('id')->toArray())->toBe([$team1->id, $team2->id]);
});

test('syncing teams replaces existing teams', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->for($tenant)->create();
    $category = Category::factory()->for($tenant)->create();
    $team1 = InternalTeam::factory()->for($tenant)->create();
    $team2 = InternalTeam::factory()->for($tenant)->create();
    $team3 = InternalTeam::factory()->for($tenant)->create();
    $user = User::factory()->for($tenant)->create();

    $action = app(SyncCategoryTeamsAction::class);
    $action->handle(
        $category,
        SyncCategoryTeamsData::fromRequest(['teams' => [$team1->id, $team2->id]]),
        $user,
    );

    $action->handle(
        $category,
        SyncCategoryTeamsData::fromRequest(['teams' => [$team3->id]]),
        $user,
    );

    expect($category->teams()->pluck('id')->toArray())->toBe([$team3->id]);
});

test('syncing empty array removes all teams', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->for($tenant)->create();
    $category = Category::factory()->for($tenant)->create();
    $team1 = InternalTeam::factory()->for($tenant)->create();
    $user = User::factory()->for($tenant)->create();

    $action = app(SyncCategoryTeamsAction::class);
    $action->handle(
        $category,
        SyncCategoryTeamsData::fromRequest(['teams' => [$team1->id]]),
        $user,
    );

    $action->handle(
        $category,
        SyncCategoryTeamsData::fromRequest(['teams' => []]),
        $user,
    );

    expect($category->teams()->count())->toBe(0);
});

test('teams are scoped to tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $location = Location::factory()->for($tenant1)->create();
    $category = Category::factory()->for($tenant1)->create();
    $team1 = InternalTeam::factory()->for($tenant1)->create();
    $team2 = InternalTeam::factory()->for($tenant2)->create();
    $user = User::factory()->for($tenant1)->create();

    $action = app(SyncCategoryTeamsAction::class);
    $action->handle(
        $category,
        SyncCategoryTeamsData::fromRequest(['teams' => [$team1->id, $team2->id]]),
        $user,
    );

    // Only tenant1's team should be synced
    expect($category->teams()->pluck('id')->toArray())->toBe([$team1->id]);
});
