<?php

declare(strict_types=1);

use App\Actions\Issues\BuildInspectionRoundCopyPrefillAction;
use App\Actions\Issues\ToggleInspectionRoundFavoriteAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Livewire\Issues\Index;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\IssueRoundStop;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{tenant: Tenant, actor: User, team: InternalTeam, issue: Issue, unitA: Unit, unitB: Unit}
 */
function favoriteRoundScaffold(): array
{
    $tenant = Tenant::factory()->create([
        'work_menu_inspection_rounds_enabled' => true,
    ]);
    $actor = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => true,
    ]);
    $category->teams()->attach($team->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unitA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_unit_checks' => true,
        'is_active' => true,
        'name' => 'Stop A',
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_unit_checks' => true,
        'is_active' => true,
        'name' => 'Stop B',
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'description' => 'Schoonmaak Brugge west',
        'approved_at' => now(),
        'is_recurring' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => 'week',
        'recurrence_lead_days' => 2,
        'is_favorite_round' => false,
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitA->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'priority' => TaskPriority::Prio2,
    ]);

    Tenancy::actAs($tenant->id);

    return compact('tenant', 'actor', 'team', 'issue', 'unitA', 'unitB');
}

it('markeert een inspectieronde als favoriet', function () {
    $ctx = favoriteRoundScaffold();

    $issue = app(ToggleInspectionRoundFavoriteAction::class)->handle(
        $ctx['issue'],
        $ctx['actor'],
        true,
    );

    expect($issue->is_favorite_round)->toBeTrue();
});

it('bouwt copy-prefill als eenmalig voor vandaag zonder uitvoerder', function () {
    $ctx = favoriteRoundScaffold();

    $prefill = app(BuildInspectionRoundCopyPrefillAction::class)->handle($ctx['issue']);

    expect($prefill['description'])->toBe('Schoonmaak Brugge west')
        ->and($prefill['is_recurring'])->toBeFalse()
        ->and($prefill['recurrence_first_due_date'])->toBe(now()->toDateString())
        ->and($prefill['round_stop_unit_ids'])->toBe([(int) $ctx['unitA']->id, (int) $ctx['unitB']->id])
        ->and($prefill['internal_team_id'])->toBe((int) $ctx['team']->id)
        ->and($prefill['assigned_worker_id'])->toBeNull()
        ->and($prefill['task_priority'])->toBe(TaskPriority::Prio2->value);
});

it('toont favoriet-filter en kopieert ronde in de plan-modal', function () {
    $ctx = favoriteRoundScaffold();
    app(ToggleInspectionRoundFavoriteAction::class)->handle($ctx['issue'], $ctx['actor'], true);

    Livewire::actingAs($ctx['actor'])
        ->withQueryParams(['inspection_round' => '1'])
        ->test(Index::class)
        ->assertSet('inspectionRoundOnly', true)
        ->assertSee(__('issues.filter.favorite_rounds_only'), false)
        ->assertSeeHtml('wp-favorite-star')
        ->assertSee(__('issues.list.copy_round'), false)
        ->call('copyRoundCreate', $ctx['issue']->id)
        ->assertSet('showRoundCreateModal', true)
        ->assertSet('is_recurring', false)
        ->assertSet('description', 'Schoonmaak Brugge west')
        ->assertSet('round_stop_unit_ids', [(int) $ctx['unitA']->id, (int) $ctx['unitB']->id])
        ->assertSet('assigned_worker_id', null);
});

it('filtert alleen favorieten inclusief gesloten rondes', function () {
    $ctx = favoriteRoundScaffold();
    $ctx['issue']->forceFill([
        'is_favorite_round' => true,
        'status' => TaskStatus::Closed,
    ])->save();

    Livewire::actingAs($ctx['actor'])
        ->withQueryParams(['inspection_round' => '1', 'favorite_rounds' => '1'])
        ->test(Index::class)
        ->assertSet('favoriteRoundsOnly', true)
        ->assertSee('Schoonmaak Brugge west', false);
});
