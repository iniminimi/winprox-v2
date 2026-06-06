<?php

use App\Actions\Categories\SyncCategoryTeamsAction;
use App\Actions\Tasks\ResolveEligibleTeamsForUnitAction;
use App\Actions\Tasks\ResolveTeamForTaskAction;
use App\Actions\Teams\SyncTeamCategoriesAction;
use App\Data\Categories\SyncCategoryTeamsData;
use App\Data\Teams\SyncTeamCategoriesData;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(fn () => Tenancy::forget());

test('can sync teams to category with is_primary validation', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $action = new SyncCategoryTeamsAction(app(\App\Support\Audit\AuditRecorder::class));

    // Sync with one primary team
    $data = SyncCategoryTeamsData::fromRequest([
        'teams' => [
            ['id' => $team1->id, 'is_primary' => true],
            ['id' => $team2->id, 'is_primary' => false],
        ],
    ]);

    $result = $action->handle($category, $data, $user);

    expect($result)->toHaveCount(2);

    // Verify only one team is primary
    $category->refresh();
    $teams = $category->teams()->withPivot('is_primary')->get();

    $primaryTeam = $teams->first(fn ($t) => (bool) $t->pivot->is_primary === true);
    $nonPrimaryTeam = $teams->first(fn ($t) => (bool) $t->pivot->is_primary === false);

    expect($primaryTeam)->not->toBeNull();
    expect($nonPrimaryTeam)->not->toBeNull();
    expect($primaryTeam->id)->toBe($team1->id);
    expect($nonPrimaryTeam->id)->toBe($team2->id);
});

test('can sync categories to team', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $category1 = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category2 = Category::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $action = new SyncTeamCategoriesAction(app(\App\Support\Audit\AuditRecorder::class));

    $data = SyncTeamCategoriesData::fromRequest([
        'categories' => [
            ['id' => $category1->id, 'is_primary' => true],
            ['id' => $category2->id, 'is_primary' => false],
        ],
    ]);

    $result = $action->handle($team, $data, $user);

    expect($result)->toHaveCount(2);

    $team->refresh();
    $categories = $team->categories()->withPivot('is_primary')->get();

    $primaryCategory = $categories->first(fn ($c) => (bool) $c->pivot->is_primary === true);
    $nonPrimaryCategory = $categories->first(fn ($c) => (bool) $c->pivot->is_primary === false);

    expect($primaryCategory)->not->toBeNull();
    expect($nonPrimaryCategory)->not->toBeNull();
    expect($primaryCategory->id)->toBe($category1->id);
    expect($nonPrimaryCategory->id)->toBe($category2->id);
});

test('resolve eligible teams for unit returns teams with is_primary flag', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $unit = Unit::factory()->create([
        'location_id' => $location->id,
        'category_id' => $category->id,
        'tenant_id' => $tenant->id,
    ]);

    // Sync teams to category
    $category->teams()->sync([
        $team1->id => ['is_primary' => true],
        $team2->id => ['is_primary' => false],
    ]);

    $action = new ResolveEligibleTeamsForUnitAction();
    $eligible = $action->handle($unit);

    expect($eligible)->toHaveCount(2);
    expect($eligible->firstWhere('team.id', $team1->id)['is_primary'])->toBeTrue();
    expect($eligible->firstWhere('team.id', $team2->id)['is_primary'])->toBeFalse();
});

test('resolve team for task chooses primary team', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $unit = Unit::factory()->create([
        'location_id' => $location->id,
        'category_id' => $category->id,
        'tenant_id' => $tenant->id,
    ]);

    // Sync teams to category with team1 as primary
    $category->teams()->sync([
        $team1->id => ['is_primary' => true],
        $team2->id => ['is_primary' => false],
    ]);

    $action = new ResolveTeamForTaskAction(new ResolveEligibleTeamsForUnitAction());
    $resolvedTeam = $action->handle($unit);

    expect($resolvedTeam->id)->toBe($team1->id);
});

test('resolve team for task falls back to default team when no primary', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $unit = Unit::factory()->create([
        'location_id' => $location->id,
        'category_id' => $category->id,
        'default_internal_team_id' => $team2->id,
        'tenant_id' => $tenant->id,
    ]);

    // Sync teams to category without primary
    $category->teams()->sync([
        $team1->id => ['is_primary' => false],
        $team2->id => ['is_primary' => false],
    ]);

    $action = new ResolveTeamForTaskAction(new ResolveEligibleTeamsForUnitAction());
    $resolvedTeam = $action->handle($unit);

    expect($resolvedTeam->id)->toBe($team2->id);
});

test('resolve team for task falls back to first eligible when no primary and no default', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $team1 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $team2 = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $unit = Unit::factory()->create([
        'location_id' => $location->id,
        'category_id' => $category->id,
        'default_internal_team_id' => null,
        'tenant_id' => $tenant->id,
    ]);

    // Sync teams to category without primary
    $category->teams()->sync([
        $team1->id => ['is_primary' => false],
        $team2->id => ['is_primary' => false],
    ]);

    $action = new ResolveTeamForTaskAction(new ResolveEligibleTeamsForUnitAction());
    $resolvedTeam = $action->handle($unit);

    expect($resolvedTeam->id)->toBe($team1->id);
});
