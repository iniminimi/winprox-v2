<?php

use App\Enums\TaskStatus;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;

/**
 * @return array{tenant: Tenant, location: Location, team: InternalTeam, unit: Unit}
 */
function esgPortalScaffold(): array
{
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ESG Category']);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('unit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    return compact('tenant', 'location', 'team', 'unit');
}

afterEach(fn () => Tenancy::forget());

it('laat een worker een ESG-taak afhandelen met numerieke meting', function () {
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = esgPortalScaffold();

    $indicator = EsgIndicator::factory()->numeric('m3')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
    ]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => now(),
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->assertSee(__('esg.portal.measurement_label', ['name' => 'Gas m3']), false)
        ->set('completingEsgValueNumeric', '123.45')
        ->set('completingRecordedAt', now()->toIso8601String())
        ->call('submitCompleteTask')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'task_done');

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and(EsgMeasurement::count())->toBe(1);

    $measurement = EsgMeasurement::query()->first();
    expect($measurement->task_id)->toBe($task->id)
        ->and($measurement->esg_indicator_id)->toBe($indicator->id)
        ->and($measurement->worker_id)->toBe($worker->id)
        ->and((float) $measurement->value_numeric)->toBe(123.0);
});

it('laat een worker een ESG-taak afhandelen met keuzelijst', function () {
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = esgPortalScaffold();

    $indicator = EsgIndicator::factory()->choice(['Restafval', 'PMD', 'Papier'])->create([
        'tenant_id' => $tenant->id,
        'name' => 'Afvalcategorie',
    ]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => now(),
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->assertSee('PMD', false)
        ->set('completingEsgValueString', 'PMD')
        ->set('completingRecordedAt', now()->toIso8601String())
        ->call('submitCompleteTask')
        ->assertHasNoErrors();

    expect(EsgMeasurement::query()->first()?->value_string)->toBe('PMD');
});

it('laat een worker een ESG-taak afhandelen met meervoudige keuze', function () {
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = esgPortalScaffold();

    $indicator = EsgIndicator::factory()->multiChoice(['Restafval', 'PMD', 'Papier'])->create([
        'tenant_id' => $tenant->id,
        'name' => 'Afvalstromen',
    ]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => now(),
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->assertSee('PMD', false)
        ->set('completingEsgValueMultiChoice', ['PMD', 'Papier'])
        ->set('completingRecordedAt', now()->toIso8601String())
        ->call('submitCompleteTask')
        ->assertHasNoErrors();

    expect(EsgMeasurement::query()->first()?->value_json)->toBe(['PMD', 'Papier']);
});

it('weigert afhandelen van een ESG-taak zonder meetwaarde', function () {
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = esgPortalScaffold();

    $indicator = EsgIndicator::factory()->numeric()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'approved_at' => now(),
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => now(),
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->set('completingRecordedAt', now()->toIso8601String())
        ->call('submitCompleteTask')
        ->assertHasErrors(['completingEsgValueNumeric']);

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and(EsgMeasurement::count())->toBe(0);
});

it('toont geen ESG-veld bij taken zonder indicator', function () {
    ['unit' => $unit, 'team' => $team, 'location' => $location, 'tenant' => $tenant] = esgPortalScaffold();

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => now(),
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->assertDontSee(__('esg.portal.numeric_placeholder'), false)
        ->set('completingNote', 'Klaar.')
        ->call('submitCompleteTask')
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and(EsgMeasurement::count())->toBe(0);
});
