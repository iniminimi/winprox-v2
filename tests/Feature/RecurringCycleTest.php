<?php

use App\Actions\Tasks\CreateRecurringTaskCycleAction;
use App\Enums\RecurrenceIntervalUnit;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\Carbon;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Tenancy::forget();
});

it('creates a recurring task cycle when due', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Month->value,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => now()->addDays(7),
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $task = $action->handle($issue, now()->addDays(7));

    expect($task)->not->toBeNull();
    expect($task->is_recurring_cycle)->toBeTrue();
    expect($task->cycle_number)->toBe(1);
    expect($task->due_at->toDateString())->toBe(now()->addDays(7)->toDateString());
    expect($task->status)->toBe(TaskStatus::New);

    assertDatabaseHas('tasks', [
        'recurrence_issue_id' => $issue->id,
        'cycle_number' => 1,
    ]);

    $issue->refresh();
    expect($issue->recurrence_next_due_at)->not->toBeNull();
});

it('does not create cycle before lead time', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_lead_days' => 30,
        'recurrence_next_due_at' => now()->addDays(60),
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $task = $action->handle($issue, now());

    expect($task)->toBeNull();
});

it('does not create cycle if already exists for due date', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $dueDate = now()->addDays(7);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => $dueDate,
    ]);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'recurrence_issue_id' => $issue->id,
        'due_at' => $dueDate,
        'is_recurring_cycle' => true,
        'cycle_number' => 1,
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $task = $action->handle($issue, now()->addDays(7));

    expect($task)->toBeNull();
});

it('closes overdue previous cycle when opening new one', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $oldDueDate = now()->subDays(10);
    $newDueDate = now()->addDays(7);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => $newDueDate,
    ]);

    $oldTask = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'recurrence_issue_id' => $issue->id,
        'due_at' => $oldDueDate,
        'is_recurring_cycle' => true,
        'cycle_number' => 1,
        'status' => TaskStatus::InProgress,
        'internal_team_id' => $team->id,
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $newTask = $action->handle($issue, now()->addDays(7));

    expect($newTask)->not->toBeNull();
    expect($newTask->cycle_number)->toBe(2);
    expect($newTask->internal_team_id)->toBe($team->id);

    $oldTask->refresh();
    expect($oldTask->status)->toBe(TaskStatus::Closed);
});

it('calculates next due date correctly for different intervals', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $now = now();

    // Week
    $issueWeek = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_interval_value' => 2,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Week->value,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => $now->copy()->addDays(7),
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $action->handle($issueWeek, $now->copy()->addDays(7));

    $issueWeek->refresh();
    expect($issueWeek->recurrence_next_due_at->toDateString())
        ->toBe($now->copy()->addDays(21)->toDateString()); // 7 + 2 weeks

    // Month
    $issueMonth = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_interval_value' => 3,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Month->value,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => $now->copy()->addDays(7),
    ]);

    $action->handle($issueMonth, $now->copy()->addDays(7));

    $issueMonth->refresh();
    expect($issueMonth->recurrence_next_due_at->toDateString())
        ->toBe($now->copy()->addDays(7)->addMonthsNoOverflow(3)->toDateString());

    // Year
    $issueYear = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Year->value,
        'recurrence_lead_days' => 30,
        'recurrence_next_due_at' => $now->copy()->addDays(30),
    ]);

    $action->handle($issueYear, $now->copy()->addDays(30));

    $issueYear->refresh();
    expect($issueYear->recurrence_next_due_at->toDateString())
        ->toBe($now->copy()->addDays(30)->addYear()->toDateString());
});

it('does not create cycle when recurrence is paused', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_paused_at' => now()->subDays(1),
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => now()->addDays(7),
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $task = $action->handle($issue, now()->addDays(7));

    expect($task)->toBeNull();
});

it('does not create cycle when recurrence is inactive', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => false,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => now()->addDays(7),
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $task = $action->handle($issue, now()->addDays(7));

    expect($task)->toBeNull();
});

it('tracks carryover from previous cycle', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $oldDueDate = now()->subDays(10);
    $newDueDate = now()->addDays(7);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => $newDueDate,
    ]);

    $oldTask = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'recurrence_issue_id' => $issue->id,
        'due_at' => $oldDueDate,
        'is_recurring_cycle' => true,
        'cycle_number' => 1,
        'status' => TaskStatus::InProgress,
        'internal_team_id' => $team->id,
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $newTask = $action->handle($issue, now()->addDays(7));

    expect($newTask)->not->toBeNull();
    expect($newTask->carryover_from_task_id)->toBe($oldTask->id);
});

it('sets not_executed_at and late_by_days for overdue cycles', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $oldDueDate = now()->subDays(5);
    $newDueDate = now()->addDays(7);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => $newDueDate,
    ]);

    $oldTask = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'recurrence_issue_id' => $issue->id,
        'due_at' => $oldDueDate,
        'is_recurring_cycle' => true,
        'cycle_number' => 1,
        'status' => TaskStatus::InProgress,
        'internal_team_id' => $team->id,
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $action->handle($issue, now());

    $oldTask->refresh();
    expect($oldTask->not_executed_at)->not->toBeNull();
    expect($oldTask->late_by_days)->toBe(5);
    expect($oldTask->status_reason)->toBe('auto_expired_due_new_cycle');
});
