<?php

declare(strict_types=1);

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Livewire\Esg\MeasurementsIndex;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Esg\EsgMeasurementPresenter;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('weigert metingen-overzicht zonder esg-module', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.measurements.index'))
        ->assertForbidden();
});

it('weigert metingen-overzicht voor niet-admins', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.measurements.index'))
        ->assertForbidden();
});

it('toont geregistreerde metingen met context', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Blok B']);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Gasmeter']);
    $indicator = EsgIndicator::factory()->numeric('m3')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
        'thresholds' => ['min' => 0, 'max' => 100],
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create(['tenant_id' => $tenant->id, 'issue_id' => $issue->id]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $task->id,
            esgIndicatorId: $indicator->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $tenant->id,
    );

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSee('Gas m3')
        ->assertSee('Blok B')
        ->assertSee('Gasmeter')
        ->assertSee(__('esg.measurements.outside_thresholds'));
});

it('toont alleen units met geregistreerde metingen in unitfilter', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Site A']);
    $measuredUnit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Gasmeter']);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Ongebruikte unit']);
    $indicator = EsgIndicator::factory()->numeric()->create(['tenant_id' => $tenant->id, 'name' => 'Gas']);

    EsgMeasurement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $measuredUnit->id,
        'esg_indicator_id' => $indicator->id,
        'value_numeric' => 12,
        'recorded_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSee('Gasmeter')
        ->assertDontSee('Ongebruikte unit');
});

it('filtert metingen op indicator', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $gas = EsgIndicator::factory()->numeric()->create(['tenant_id' => $tenant->id, 'name' => 'Gas']);
    $water = EsgIndicator::factory()->numeric()->create(['tenant_id' => $tenant->id, 'name' => 'Water']);

    foreach ([$gas, $water] as $indicator) {
        $issue = Issue::factory()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'unit_id' => $unit->id,
            'esg_indicator_id' => $indicator->id,
            'is_recurring' => true,
        ]);
        $task = Task::factory()->create(['tenant_id' => $tenant->id, 'issue_id' => $issue->id]);
        app(RecordEsgMeasurementAction::class)->handle(
            new RecordEsgMeasurementData(
                taskId: $task->id,
                esgIndicatorId: $indicator->id,
                recordedAt: now()->toImmutable(),
                valueNumeric: 10.0,
            ),
            $tenant->id,
        );
    }

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class, ['indicatorFilter' => $gas->id])
        ->assertSee('<strong>Gas</strong>', false)
        ->assertDontSee('<strong>Water</strong>', false);
});

it('toont vertaalde indicatornaam in meetoverzicht volgens gebruikerstaal', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'locale' => 'en']);
    Tenancy::actAs($tenant->id);

    $indicator = EsgIndicator::factory()->numeric('kWh')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Elektriciteit',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(\App\Actions\Communication\ImportEsgIndicatorTranslationsAction::class)->handle([
        [
            'esg_indicator_id' => $indicator->id,
            'locale' => 'en',
            'name' => 'Electricity',
        ],
    ]);

    EsgMeasurement::factory()->create([
        'tenant_id' => $tenant->id,
        'esg_indicator_id' => $indicator->id,
        'value_numeric' => 100,
        'recorded_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSee('<strong>Electricity</strong>', false)
        ->assertDontSee('<strong>Elektriciteit</strong>', false);
});

it('formateert meetwaarden voor weergave', function () {
    $indicator = EsgIndicator::factory()->numeric('kWh')->make(['name' => 'Stroom']);
    $measurement = EsgMeasurement::factory()->make([
        'value_numeric' => 1234.5,
        'esg_indicator_id' => 1,
    ]);
    $measurement->setRelation('indicator', $indicator);

    expect(EsgMeasurementPresenter::displayValue($measurement))->toBe('1.234,5 kWh');
});

it('toont verouderde keuzewaarden in rapportage', function () {
    $indicator = EsgIndicator::factory()->choice(['Restafval', 'Papier'])->make(['name' => 'Afval']);
    $measurement = EsgMeasurement::factory()->make([
        'value_string' => 'PMD',
        'esg_indicator_id' => 1,
    ]);
    $measurement->setRelation('indicator', $indicator);

    expect(EsgMeasurementPresenter::displayValue($measurement))
        ->toBe(__('esg.measurements.legacy_choice_value', ['value' => 'PMD']));
});

it('formateert meervoudige keuzewaarden voor weergave', function () {
    $indicator = EsgIndicator::factory()->multiChoice(['Restafval', 'Papier'])->make(['name' => 'Afval']);
    $measurement = EsgMeasurement::factory()->make([
        'value_json' => ['PMD', 'Papier'],
        'esg_indicator_id' => 1,
    ]);
    $measurement->setRelation('indicator', $indicator);

    expect(EsgMeasurementPresenter::displayValue($measurement))
        ->toBe(__('esg.measurements.legacy_choice_value', ['value' => 'PMD']).', Papier');
});

it('toont vertaalde keuzewaarden in rapportage volgens actieve taal', function () {
    app()->setLocale('en');

    $indicator = EsgIndicator::factory()->choice(['Restafval', 'Papier'])->make([
        'name' => 'Afval',
        'original_language' => 'nl',
    ]);
    $indicator->setRelation('translations', collect([
        \App\Models\EsgIndicatorTranslation::make([
            'locale' => 'en',
            'status' => \App\Enums\EsgIndicatorTranslationStatus::Completed,
            'name' => 'Waste',
            'options' => ['Residual waste', 'Paper'],
        ]),
    ]));

    $measurement = EsgMeasurement::factory()->make([
        'value_string' => 'Restafval',
        'esg_indicator_id' => 1,
    ]);
    $measurement->setRelation('indicator', $indicator);

    expect(EsgMeasurementPresenter::displayValue($measurement))->toBe('Residual waste');
});

it('toont setup-stappen zonder metingen', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSee(__('esg.measurements.setup.title'))
        ->assertSee(__('esg.measurements.setup.steps')[0], false);
});
