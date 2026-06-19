<?php

use App\Actions\Communication\EnsureTaskTranslationSlotsAction;
use App\Actions\Communication\ExportPendingTaskTranslationsAction;
use App\Actions\Communication\ImportTaskTranslationsAction;
use App\Actions\Communication\TranslateTaskAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskDetailsAction;
use App\Enums\TaskTranslationStatus;
use App\Livewire\Tasks\Index;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\TaskTranslation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na aanmaken taak met omschrijving', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Kraan lekt',
        'original_language' => 'nl',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);
    Tenancy::actAs($tenant->id);

    $task = app(CreateTaskAction::class)->handle($issue, $team->id, description: 'Vervang pakking');

    $rows = TaskTranslation::query()->where('task_id', $task->id)->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe(['de', 'en', 'fr'])
        ->and($rows->every(fn ($row) => $row->status === TaskTranslationStatus::Pending))->toBeTrue();
});

it('maakt geen vertaalrijen voor taak zonder omschrijving', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'original_language' => 'nl',
        'approved_at' => now(),
    ]);
    Tenancy::actAs($tenant->id);

    $task = app(CreateTaskAction::class)->handle($issue, $team->id);

    expect(TaskTranslation::query()->where('task_id', $task->id)->count())->toBe(0);
});

it('vertaalt taak via de provider', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'original_language' => 'nl',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);
    Tenancy::actAs($tenant->id);

    $task = app(CreateTaskAction::class)->handle($issue, $team->id, description: 'Vervang pakking');

    $row = app(TranslateTaskAction::class)->handle($task, 'en', $user->id);

    expect($row->status)->toBe(TaskTranslationStatus::Completed)
        ->and($row->description)->toBe('[en] Vervang pakking')
        ->and($task->fresh()->localizedDescription('en'))->toBe('[en] Vervang pakking');
});

it('exporteert en importeert pending taakvertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'original_language' => 'nl',
        'approved_at' => now(),
    ]);
    Tenancy::actAs($tenant->id);

    $task = app(CreateTaskAction::class)->handle($issue, $team->id, description: 'Vervang pakking');

    $exportItems = app(ExportPendingTaskTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(3)
        ->and($exportItems[0])->toHaveKeys(['task_id', 'source_text', 'locale']);

    $imported = app(ImportTaskTranslationsAction::class)->handle([
        [
            'task_id' => $task->id,
            'locale' => 'en',
            'description' => 'Replace gasket',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($task->fresh()->localizedDescription('en'))->toBe('Replace gasket');
});

it('invalideert vertalingen bij wijziging taakomschrijving', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'original_language' => 'nl',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);
    Tenancy::actAs($tenant->id);

    $task = app(CreateTaskAction::class)->handle($issue, $team->id, description: 'Vervang pakking');
    app(TranslateTaskAction::class)->handle($task, 'en', $user->id);

    app(UpdateTaskDetailsAction::class)->handle(
        $task,
        'Nieuwe pakking monteren',
        null,
        $tenant->id,
        $user->id,
    );

    $english = TaskTranslation::query()
        ->where('task_id', $task->id)
        ->where('locale', 'en')
        ->first();

    expect($english->status)->toBe(TaskTranslationStatus::Pending)
        ->and($english->description)->toBeNull();
});

it('weigert import van taak zonder omschrijving', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'original_language' => 'nl', 'approved_at' => now()]);
    Tenancy::actAs($tenant->id);

    $task = app(CreateTaskAction::class)->handle($issue, $team->id);

    expect(fn () => app(ImportTaskTranslationsAction::class)->handle([
        [
            'task_id' => $task->id,
            'locale' => 'en',
            'description' => 'Replace gasket',
        ],
    ]))->toThrow(ValidationException::class);
});

it('toont vertaalde taakomschrijving in takenlijst volgens gebruikerstaal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en', 'role' => User::ROLE_ADMIN]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'original_language' => 'nl',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);
    Tenancy::actAs($tenant->id);

    $task = app(CreateTaskAction::class)->handle($issue, $team->id, description: 'Vervang pakking');
    app(ImportTaskTranslationsAction::class)->handle([
        [
            'task_id' => $task->id,
            'locale' => 'en',
            'description' => 'Replace gasket',
        ],
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Replace gasket');
});
