<?php

use App\Actions\Tasks\CreateRecurringTaskCycleAction;
use App\Enums\RecurrenceIntervalUnit;
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

afterEach(function () {
    Carbon::setTestNow();
});

it('shows recurring tasks in calendar view', function () {
    // Vastzetten midden in de maand zodat due+7 binnen de maandgrid blijft.
    Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00'));

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = \App\Models\Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    $dueDate = now()->addDays(7);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Month->value,
        'recurrence_lead_days' => 7,
        'recurrence_next_due_at' => $dueDate,
    ]);

    $action = app(CreateRecurringTaskCycleAction::class);
    $task = $action->handle($issue, now()->addDays(7));

    expect($task)->not->toBeNull();
    expect($task->due_at->toDateString())->toBe($dueDate->toDateString());
    expect($task->scheduled_for->toDateString())->toBe($dueDate->toDateString());

    // Verify the task appears in calendar query
    $gridStart = now()->startOfMonth();
    $gridEnd = now()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

    $tasks = Task::query()
        ->with(['issue.location', 'issue.unit', 'team'])
        ->where(function ($q) use ($gridStart, $gridEnd) {
            $q->where(function ($sub) use ($gridStart, $gridEnd) {
                $sub->whereNotNull('scheduled_for')
                    ->whereBetween('scheduled_for', [$gridStart->toDateString(), $gridEnd->toDateString()]);
            })->orWhere(function ($sub) use ($gridStart, $gridEnd) {
                $sub->whereNotNull('due_at')
                    ->whereBetween('due_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()]);
            });
        })
        ->get();

    expect($tasks->contains($task))->toBeTrue();
});
