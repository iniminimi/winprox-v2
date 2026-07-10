<?php

use App\Actions\Communication\EnsureEsgIndicatorTranslationSlotsAction;
use App\Actions\Communication\ExportPendingEsgIndicatorTranslationsAction;
use App\Actions\Communication\ImportEsgIndicatorTranslationsAction;
use App\Actions\Communication\TranslateEsgIndicatorAction;
use App\Actions\Esg\CreateEsgIndicatorAction;
use App\Enums\EsgIndicatorTranslationStatus;
use App\Enums\EsgIndicatorType;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na aanmaken actieve ESG-indicator', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true, 'trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $indicator = app(CreateEsgIndicatorAction::class)->handle($tenant->id, [
        'name' => 'Gas m3',
        'type' => EsgIndicatorType::Numeric,
        'unit_of_measure' => 'm3',
        'original_language' => 'nl',
    ]);

    $rows = EsgIndicatorTranslation::query()->where('esg_indicator_id', $indicator->id)->get();
    $expectedLocales = collect(expectedTargetLocales('nl'))->sort()->values()->all();

    expect($rows)->toHaveCount(count($expectedLocales))
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe($expectedLocales)
        ->and($rows->every(fn ($row) => $row->status === EsgIndicatorTranslationStatus::Pending))->toBeTrue();
});

it('exporteert en importeert pending ESG-indicatorvertalingen', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true, 'trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $indicator = EsgIndicator::factory()->choice(['Restafval', 'PMD'])->create([
        'tenant_id' => $tenant->id,
        'name' => 'Afvalcategorie',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureEsgIndicatorTranslationSlotsAction::class)->handle($indicator);

    $exportItems = app(ExportPendingEsgIndicatorTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($exportItems[0])->toHaveKeys(['esg_indicator_id', 'source_name', 'source_options', 'locale']);

    $imported = app(ImportEsgIndicatorTranslationsAction::class)->handle([
        [
            'esg_indicator_id' => $indicator->id,
            'locale' => 'en',
            'name' => 'Waste category',
            'options' => ['Residual waste', 'PMD'],
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($indicator->fresh()->localizedName('en'))->toBe('Waste category')
        ->and($indicator->fresh()->localizedChoiceOptionLabel('Restafval', 'en'))->toBe('Residual waste');
});

it('vertaalt ESG-indicator via de provider', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true, 'trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $indicator = EsgIndicator::factory()->numeric('kWh')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Elektriciteit',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureEsgIndicatorTranslationSlotsAction::class)->handle($indicator);
    app(TranslateEsgIndicatorAction::class)->handle($indicator, 'en');

    $row = EsgIndicatorTranslation::query()->where('esg_indicator_id', $indicator->id)->where('locale', 'en')->first();

    expect($row->status)->toBe(EsgIndicatorTranslationStatus::Completed)
        ->and($row->name)->toBe('[en] Elektriciteit');
});

it('weigert import van te lange ESG-indicatornaam', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true, 'trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $indicator = EsgIndicator::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureEsgIndicatorTranslationSlotsAction::class)->handle($indicator);

    expect(fn () => app(ImportEsgIndicatorTranslationsAction::class)->handle([
        [
            'esg_indicator_id' => $indicator->id,
            'locale' => 'en',
            'name' => str_repeat('x', 256),
        ],
    ]))->toThrow(ValidationException::class);
});

it('toont vertaalde ESG-indicatornaam in portaal met locale cookie', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('unit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $indicator = EsgIndicator::factory()->numeric('m3')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    $worker = Worker::factory()->create([
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
        'started_at' => now(),
    ]);

    app(EnsureEsgIndicatorTranslationSlotsAction::class)->handle($indicator);
    app(ImportEsgIndicatorTranslationsAction::class)->handle([
        [
            'esg_indicator_id' => $indicator->id,
            'locale' => 'en',
            'name' => 'Gas m3 EN',
        ],
    ]);

    WorkerVerification::markVerified($team, $worker);

    Livewire::withCookies([\App\Support\ResolveAppLocale::COOKIE_NAME => 'en'])
        ->test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('beginCompleteTask', $task->id)
        ->assertSee(__('esg.portal.measurement_label', ['name' => 'Gas m3 EN']), false);
});
