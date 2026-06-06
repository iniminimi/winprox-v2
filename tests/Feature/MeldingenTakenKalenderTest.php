<?php

use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\IssueSource;
use App\Enums\TaskStatus;
use App\Livewire\Issues\Index as IssueIndex;
use App\Livewire\Pages\Calendar;
use App\Livewire\Tasks\Show as TaskShow;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Unit;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\IssuePhoto;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('maakt een melding aan via 2-staps flow met taak in uitvoering', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true)
        ->set('location_id', $location->id)
        ->set('description', 'Lekkende kraan in keuken')
        ->call('saveCreateStepOne')
        ->assertHasNoErrors()
        ->assertSet('createStep', 2)
        ->set('internal_team_id', $team->id)
        ->set('task_note', 'Direct aanpakken')
        ->call('saveCreateStepTwo')
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

it('toont validatiefouten bij lege stap 1 van aanmaak-modal', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->call('saveCreateStepOne')
        ->assertHasErrors(['location_id', 'description'])
        ->assertSet('createStep', 1);

    expect(Issue::count())->toBe(0);
});

it('toont validatiefout bij stap 2 zonder team', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('location_id', $location->id)
        ->set('description', 'Lekkende kraan')
        ->call('saveCreateStepOne')
        ->assertHasNoErrors()
        ->assertSet('createStep', 2)
        ->call('saveCreateStepTwo')
        ->assertHasErrors(['internal_team_id'])
        ->assertSet('createStep', 2);
});

it('vult standaardteam in bij unit met category die teams heeft', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Techniek']);
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kranen']);
    $category->teams()->sync([$team->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
    ]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('unit_id', $unit->id)
        ->assertSet('internal_team_id', $team->id);
});

it('slaat foto\'s op bij aanmaken melding via modal', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $photo = UploadedFile::fake()->create('melding.jpg', 120, 'image/jpeg');

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('location_id', $location->id)
        ->set('description', 'Schade met foto')
        ->set('photos', [$photo])
        ->call('saveCreateStepOne')
        ->assertHasNoErrors()
        ->set('internal_team_id', $team->id)
        ->call('saveCreateStepTwo');

    $issue = Issue::first();

    expect($issue)->not->toBeNull()
        ->and(IssuePhoto::query()->where('issue_id', $issue->id)->count())->toBe(1)
        ->and(Storage::disk('public')->exists(IssuePhoto::first()->path))->toBeTrue();
});

it('opent de aanmaak-modal via create-query en oude route', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $this->actingAs($user)
        ->get(route('issues.create'))
        ->assertRedirect(route('issues.index', ['create' => 1]));

    Livewire::actingAs($user)
        ->withQueryParams(['create' => '1'])
        ->test(IssueIndex::class)
        ->assertSet('showCreateModal', true);
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

it('toont NR-referentie op melding- en taakdetail', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    $issueNr = __('issues.card.nr', ['nr' => $issue->id]);
    $taskNr = __('tasks.card.nr', ['nr' => $task->id]);

    $this->actingAs($user)
        ->get(route('issues.show', $issue))
        ->assertOk()
        ->assertSee($issueNr, false);

    $this->actingAs($user)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertSee($taskNr, false)
        ->assertSee($issueNr, false);
});

it('wijzigt het team op taakdetail', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Elektriciteit']);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Techniek']);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Kolombor schilderen',
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $teamA->id,
    ]);

    Livewire::actingAs($user)
        ->test(TaskShow::class, ['task' => $task])
        ->assertSee('Elektriciteit')
        ->set('teamId', $teamB->id)
        ->call('saveTeam')
        ->assertHasNoErrors();

    expect($task->fresh()->internal_team_id)->toBe($teamB->id);
});
