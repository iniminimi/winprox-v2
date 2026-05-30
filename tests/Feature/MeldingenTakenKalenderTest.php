<?php

use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\IssueSource;
use App\Enums\TaskStatus;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Issues\Index as IssueIndex;
use App\Livewire\Pages\Calendar;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('maakt een melding aan via 2-staps flow met taak in uitvoering', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueCreate::class)
        ->set('location_id', $location->id)
        ->set('description', 'Lekkende kraan in keuken')
        ->call('saveStepOne')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->set('internal_team_id', $team->id)
        ->set('task_note', 'Direct aanpakken')
        ->call('saveStepTwo')
        ->assertRedirect(route('issues.index', ['highlight' => Issue::first()->id]));

    $issue = Issue::first();
    expect($issue)->not->toBeNull()
        ->and($issue->source)->toBe(IssueSource::Manager)
        ->and($issue->isApproved())->toBeTrue()
        ->and($issue->status)->toBe(TaskStatus::InProgress)
        ->and($issue->tasks)->toHaveCount(1)
        ->and($issue->tasks->first()->status)->toBe(TaskStatus::InProgress)
        ->and($issue->tasks->first()->internal_team_id)->toBe($team->id)
        ->and($issue->tasks->first()->note)->toBe('Direct aanpakken');
});

it('filtert terugkerende meldingen op de index', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Eenmalige melding',
        'is_recurring' => false,
        'approved_at' => now(),
    ]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Terugkerend onderhoud',
        'is_recurring' => true,
        'approved_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->set('recurring', true)
        ->assertSee('Terugkerend onderhoud')
        ->assertDontSee('Eenmalige melding');
});

it('vereist een reden bij sluiten zonder uitvoering', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
    ]);

    expect(fn () => app(UpdateTaskStatusAction::class)->handle(
        $task,
        TaskStatus::Closed,
        $user,
        null,
    ))->toThrow(Illuminate\Validation\ValidationException::class);

    app(UpdateTaskStatusAction::class)->handle(
        $task,
        TaskStatus::Closed,
        $user,
        'Niet uitgevoerd: onderdeel niet geleverd',
    );

    expect($task->fresh()->status)->toBe(TaskStatus::Closed)
        ->and($issue->updates()->where('kind', 'status_reason')->exists())->toBeTrue();
});

it('opent een terugkerende cyclus via het artisan commando', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $dueAt = now()->addDays(10)->endOfDay();

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Jaarlijks onderhoud',
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => 'year',
        'recurrence_lead_days' => 30,
        'recurrence_next_due_at' => $dueAt,
        'approved_at' => now(),
    ]);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
    ]);

    Artisan::call('winprox:recurrence-tick');

    $cycle = Task::query()->where('is_recurring_cycle', true)->first();

    expect($cycle)->not->toBeNull()
        ->and($cycle->cycle_number)->toBe(1)
        ->and($cycle->due_at?->toDateString())->toBe($dueAt->toDateString())
        ->and($issue->fresh()->recurrence_next_due_at?->toDateString())->toBe($dueAt->copy()->addYear()->toDateString());
});

it('toont een geplande taak op de kalender op de juiste dag', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Kalender taak test',
        'approved_at' => now(),
    ]);

    $scheduled = now()->addDays(3);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'status' => TaskStatus::New,
        'scheduled_for' => $scheduled->toDateString(),
        'due_at' => $scheduled,
        'is_recurring_cycle' => true,
    ]);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('currentDate', $scheduled->copy()->startOfMonth()->toDateString())
        ->set('viewMode', 'month')
        ->set('entryType', 'tasks')
        ->assertSee('Kalender taak test');
});
