<?php

declare(strict_types=1);

use App\Actions\Issues\CloseIssueAction;
use App\Actions\Issues\EndRecurringIssueAction;
use App\Actions\Issues\ReopenIssueAction;
use App\Actions\Tasks\CreateRecurringTaskCycleAction;
use App\Enums\RecurrenceIntervalUnit;
use App\Enums\TaskStatus;
use App\Livewire\Issues\Show as IssueShow;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{tenant: Tenant, user: User, team: InternalTeam, issue: Issue, task: Task}
 */
function endRecurringScaffold(): array
{
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Month,
        'recurrence_lead_days' => 14,
        'recurrence_active' => true,
        'recurrence_next_due_at' => now()->subDay(),
        'recurrence_paused_at' => null,
        'approved_at' => now(),
        'status' => TaskStatus::InProgress,
    ]);

    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'is_recurring_cycle' => true,
        'recurrence_issue_id' => $issue->id,
        'due_at' => now()->subDay(),
    ]);

    return compact('tenant', 'user', 'team', 'issue', 'task');
}

it('ends a recurring issue: closes open tasks, stops cycles, sets Closed', function () {
    ['user' => $user, 'issue' => $issue, 'task' => $task] = endRecurringScaffold();

    // Historical done cycle would otherwise keep rollup at Done.
    Task::factory()->create([
        'tenant_id' => $issue->tenant_id,
        'issue_id' => $issue->id,
        'internal_team_id' => $task->internal_team_id,
        'status' => TaskStatus::Done,
        'is_recurring_cycle' => true,
        'recurrence_issue_id' => $issue->id,
        'completed_at' => now()->subMonth(),
    ]);

    $ended = app(EndRecurringIssueAction::class)->handle($issue, $user, 'Contract afgelopen');

    expect($ended->recurrence_active)->toBeFalse()
        ->and($ended->status)->toBe(TaskStatus::Closed)
        ->and($task->fresh()->status)->toBe(TaskStatus::Closed)
        ->and(app(CreateRecurringTaskCycleAction::class)->handle($ended->fresh()))->toBeNull();
});

it('ends a recurring issue from the issue detail modal', function () {
    ['user' => $user, 'issue' => $issue] = endRecurringScaffold();

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->call('openEndRecurringModal')
        ->assertSet('showEndRecurringModal', true)
        ->set('endReason', 'Niet meer nodig')
        ->call('endRecurringIssue')
        ->assertHasNoErrors()
        ->assertSet('showEndRecurringModal', false);

    $fresh = $issue->fresh();
    expect($fresh->recurrence_active)->toBeFalse()
        ->and($fresh->status)->toBe(TaskStatus::Closed);
});

it('reactivates recurrence when reopening an ended recurring issue', function () {
    ['user' => $user, 'issue' => $issue] = endRecurringScaffold();

    app(EndRecurringIssueAction::class)->handle($issue, $user, 'Stoppen');
    $reopened = app(ReopenIssueAction::class)->handle($issue->fresh(), $user, 'Toch verder');

    expect($reopened->status)->toBe(TaskStatus::New)
        ->and($reopened->recurrence_active)->toBeTrue()
        ->and($reopened->recurrence_paused_at)->toBeNull();
});

it('still refuses CloseIssueAction on approved recurring issues', function () {
    ['user' => $user, 'issue' => $issue] = endRecurringScaffold();

    expect(fn () => app(CloseIssueAction::class)->handle($issue, $user, 'Probeer te sluiten'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('still allows pause and resume on an active recurring issue', function () {
    ['user' => $user, 'issue' => $issue] = endRecurringScaffold();

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->call('toggleRecurrencePause')
        ->assertHasNoErrors();

    expect($issue->fresh()->recurrence_paused_at)->not->toBeNull()
        ->and($issue->fresh()->recurrence_active)->toBeTrue();
});
