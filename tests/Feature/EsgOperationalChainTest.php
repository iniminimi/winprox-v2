<?php

declare(strict_types=1);

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Livewire\Esg\PointHistory;
use App\Livewire\Tasks\Show as TaskShow;
use App\Models\EsgMeasurement;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Esg\EsgOperationChainPresenter;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('filtert metingen op drempelalarmen', function () {
    $fixture = esgThresholdFixture(thresholdMax: 100.0);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->subDay()->toImmutable(),
            valueNumeric: 80.0,
        ),
        $fixture['tenant']->id,
    );

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    $admin = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    $paginator = app(\App\Actions\Esg\ListEsgMeasurementsAction::class)->handle(
        $fixture['tenant']->id,
        alarmsOnly: true,
    );

    expect($paginator->total())->toBe(1)
        ->and($paginator->first()?->value_numeric)->toBe('150.0000');

    Livewire::actingAs($admin)
        ->withQueryParams(['alarms' => 1])
        ->test(\App\Livewire\Esg\MeasurementsIndex::class)
        ->assertSet('alarmsOnly', true)
        ->assertSee('150');
});

it('toont meetpunthistoriek per unit met trend en tijdlijn', function () {
    $fixture = esgThresholdFixture();

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 42.0,
        ),
        $fixture['tenant']->id,
    );

    $admin = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    $this->actingAs($admin)
        ->get(route('esg.point.history', ['unit' => $fixture['unit']->id]))
        ->assertOk()
        ->assertSee(__('esg.point.title'))
        ->assertSee('Gasmeter')
        ->assertSee('42')
        ->assertSee(__('esg.point.timeline.title'));
});

it('bouwt operationele keten voor meting met opvolgtaak', function () {
    $fixture = esgThresholdFixture();

    $measurement = app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    $measurement->load(['indicator.translations', 'task', 'thresholdFollowUpTask']);

    $steps = EsgOperationChainPresenter::stepsForMeasurement($measurement);

    expect($steps)->toHaveCount(4)
        ->and(collect($steps)->pluck('key')->all())->toBe([
            'measurement',
            'alarm',
            'measurement_task',
            'follow_up_task',
        ]);
});

it('toont operationele keten op taakdetail voor opvolgtaak', function () {
    $fixture = esgThresholdFixture();

    $measurement = app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    $followUp = Task::query()
        ->where('esg_threshold_measurement_id', $measurement->id)
        ->firstOrFail();

    $admin = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    $this->actingAs($admin)
        ->get(route('tasks.show', $followUp))
        ->assertOk()
        ->assertSee(__('esg.chain.title'))
        ->assertSee(__('esg.chain.follow_up_task'));
});

it('linkt dashboard drempel-kpi naar gefilterde metingen', function () {
    $fixture = esgThresholdFixture();

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    $admin = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Esg\Dashboard::class)
        ->assertSee(route('esg.measurements.index', ['alarms' => 1]), false);
});

it('toont esg-historiek link op locatie-unit met metingen', function () {
    $fixture = esgThresholdFixture();

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 12.0,
        ),
        $fixture['tenant']->id,
    );

    $admin = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    $this->actingAs($admin)
        ->get(route('locations.show', $fixture['location']))
        ->assertOk()
        ->assertSee(__('esg.point.link'))
        ->assertSee(route('esg.point.history', ['unit' => $fixture['unit']->id]), false);
});
