<?php

declare(strict_types=1);

use App\Enums\RecurrenceIntervalUnit;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Livewire\Tasks\Show as TasksShow;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

beforeEach(fn () => Tenancy::forget());

it('marks tasks as recurring when the parent issue is recurring', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Month,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'is_recurring_cycle' => false,
    ]);

    expect($task->isRecurring())->toBeTrue();
});

it('shows the recurring pill on the tasks list for recurring issues', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    seedTenantPastOnboarding($tenant);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Week,
        'approved_at' => now(),
    ]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'is_recurring_cycle' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(TasksIndex::class)
        ->assertSee(__('tasks.card.recurring'));
});

it('shows recurring context on the task detail page', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_interval_value' => 2,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Month,
        'recurrence_next_due_at' => now()->addMonth(),
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'is_recurring_cycle' => true,
        'cycle_number' => 3,
        'recurrence_issue_id' => $issue->id,
    ]);

    $this->actingAs($user);

    Livewire::test(TasksShow::class, ['task' => $task])
        ->assertSee(__('tasks.show.recurring_title'))
        ->assertSee(__('tasks.show.recurring_cycle', ['nr' => 3]))
        ->assertSee(__('tasks.card.recurring'));
});
