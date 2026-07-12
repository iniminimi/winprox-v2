<?php

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\EsgIndicatorType;
use App\Http\Requests\Esg\RecordEsgMeasurementRequest;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @return array{
 *     tenant: Tenant,
 *     location: Location,
 *     unit: Unit,
 *     indicator: EsgIndicator,
 *     issue: Issue,
 *     task: Task
 * }
 */
function esgMeasurementFixture(EsgIndicatorType $type = EsgIndicatorType::Numeric): array
{
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $indicator = match ($type) {
        EsgIndicatorType::Numeric => EsgIndicator::factory()->numeric()->create(['tenant_id' => $tenant->id]),
        EsgIndicatorType::Boolean => EsgIndicator::factory()->boolean()->create(['tenant_id' => $tenant->id]),
        EsgIndicatorType::String => EsgIndicator::factory()->string()->create(['tenant_id' => $tenant->id]),
        EsgIndicatorType::Choice => EsgIndicator::factory()->choice(['Restafval', 'PMD', 'Papier'])->create(['tenant_id' => $tenant->id]),
        EsgIndicatorType::MultiChoice => EsgIndicator::factory()->multiChoice(['Restafval', 'PMD', 'Papier'])->create(['tenant_id' => $tenant->id]),
        EsgIndicatorType::Json => EsgIndicator::factory()->json()->create(['tenant_id' => $tenant->id]),
    };
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'location_id' => $location->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    return compact('tenant', 'location', 'unit', 'indicator', 'issue', 'task');
}

it('slaat een numerieke ESG-meting append-only op', function () {
    $fixture = esgMeasurementFixture();
    $recordedAt = CarbonImmutable::parse('2026-07-01 08:30:00');

    $data = new RecordEsgMeasurementData(
        taskId: $fixture['task']->id,
        esgIndicatorId: $fixture['indicator']->id,
        recordedAt: $recordedAt,
        valueNumeric: 42.5,
    );

    $measurement = app(RecordEsgMeasurementAction::class)->handle(
        $data,
        $fixture['tenant']->id,
    );

    expect(EsgMeasurement::query()->whereKey($measurement->id)->exists())->toBeTrue()
        ->and($measurement->tenant_id)->toBe($fixture['tenant']->id)
        ->and($measurement->unit_id)->toBe($fixture['unit']->id)
        ->and($measurement->location_id)->toBe($fixture['location']->id)
        ->and($measurement->task_id)->toBe($fixture['task']->id)
        ->and($measurement->esg_indicator_id)->toBe($fixture['indicator']->id)
        ->and((float) $measurement->value_numeric)->toBe(43.0)
        ->and($measurement->value_boolean)->toBeNull()
        ->and($measurement->recorded_at?->toIso8601String())->toBe($recordedAt->toIso8601String())
        ->and($measurement->created_at)->not->toBeNull();

    expect(Schema::hasColumn('esg_measurements', 'updated_at'))->toBeFalse()
        ->and($measurement->timestamps)->toBeFalse();
});

it('ondersteunt boolean-, tekst- en json-indicatoren', function (EsgIndicatorType $type, array $payload, mixed $expected) {
    $fixture = esgMeasurementFixture($type);
    $validated = [
        'task_id' => $fixture['task']->id,
        'esg_indicator_id' => $fixture['indicator']->id,
        'recorded_at' => now()->toIso8601String(),
        ...$payload,
    ];

    RecordEsgMeasurementRequest::assertValueMatchesIndicator($validated, $fixture['indicator']);
    $data = RecordEsgMeasurementData::fromValidated($validated);

    $measurement = app(RecordEsgMeasurementAction::class)->handle(
        $data,
        $fixture['tenant']->id,
    );

    expect($data->valueForType($type))->toBe($expected)
        ->and($measurement->{$type->valueColumn()})->toEqual($expected);
})->with([
    'boolean true' => [EsgIndicatorType::Boolean, ['value_boolean' => true], true],
    'boolean false' => [EsgIndicatorType::Boolean, ['value_boolean' => false], false],
    'string' => [EsgIndicatorType::String, ['value_string' => 'OK'], 'OK'],
    'choice' => [EsgIndicatorType::Choice, ['value_string' => 'PMD'], 'PMD'],
    'multi_choice' => [EsgIndicatorType::MultiChoice, ['value_json' => ['PMD', 'Restafval']], ['PMD', 'Restafval']],
    'json' => [EsgIndicatorType::Json, ['value_json' => ['reading' => 12.3]], ['reading' => 12.3]],
]);

it('weigert een keuzewaarde buiten de opties', function () {
    $fixture = esgMeasurementFixture(EsgIndicatorType::Choice);
    $validated = [
        'task_id' => $fixture['task']->id,
        'esg_indicator_id' => $fixture['indicator']->id,
        'recorded_at' => now()->toIso8601String(),
        'value_string' => 'Chemisch afval',
    ];

    expect(fn () => RecordEsgMeasurementRequest::assertValueMatchesIndicator($validated, $fixture['indicator']))
        ->toThrow(ValidationException::class);
});

it('weigert meerdere of verkeerde waardekolommen', function () {
    $fixture = esgMeasurementFixture();
    $validated = [
        'task_id' => $fixture['task']->id,
        'esg_indicator_id' => $fixture['indicator']->id,
        'recorded_at' => now()->toIso8601String(),
        'value_numeric' => 10,
        'value_string' => 'oops',
    ];

    expect(fn () => RecordEsgMeasurementRequest::assertValueMatchesIndicator($validated, $fixture['indicator']))
        ->toThrow(ValidationException::class);
});

it('weigert een indicator die niet bij de melding hoort', function () {
    $fixture = esgMeasurementFixture();
    $otherIndicator = EsgIndicator::factory()->numeric()->create(['tenant_id' => $fixture['tenant']->id]);

    $data = new RecordEsgMeasurementData(
        taskId: $fixture['task']->id,
        esgIndicatorId: $otherIndicator->id,
        recordedAt: now()->toImmutable(),
        valueNumeric: 1.0,
    );

    app(RecordEsgMeasurementAction::class)->handle($data, $fixture['tenant']->id);
})->throws(ValidationException::class);

it('weigert een taak van een andere tenant', function () {
    $fixture = esgMeasurementFixture();
    $otherTenant = Tenant::factory()->create(['has_esg_module' => true]);

    $data = new RecordEsgMeasurementData(
        taskId: $fixture['task']->id,
        esgIndicatorId: $fixture['indicator']->id,
        recordedAt: now()->toImmutable(),
        valueNumeric: 1.0,
    );

    app(RecordEsgMeasurementAction::class)->handle($data, $otherTenant->id);
})->throws(ValidationException::class);

it('weigert metingen zonder ESG-module', function () {
    $fixture = esgMeasurementFixture();
    $fixture['tenant']->update(['has_esg_module' => false]);

    $data = new RecordEsgMeasurementData(
        taskId: $fixture['task']->id,
        esgIndicatorId: $fixture['indicator']->id,
        recordedAt: now()->toImmutable(),
        valueNumeric: 1.0,
    );

    app(RecordEsgMeasurementAction::class)->handle($data, $fixture['tenant']->id);
})->throws(ValidationException::class);

it('koppelt optioneel een worker en correctie', function () {
    $fixture = esgMeasurementFixture();
    $worker = Worker::factory()->create(['tenant_id' => $fixture['tenant']->id]);

    $original = app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->subHour()->toImmutable(),
            valueNumeric: 10.0,
        ),
        $fixture['tenant']->id,
        workerId: $worker->id,
    );

    $correction = app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 11.0,
            correctsMeasurementId: $original->id,
        ),
        $fixture['tenant']->id,
        workerId: $worker->id,
    );

    expect(EsgMeasurement::count())->toBe(2)
        ->and($correction->corrects_measurement_id)->toBe($original->id)
        ->and($correction->worker_id)->toBe($worker->id);
});

it('valideert tenant-scope in het request ruleset', function () {
    $fixture = esgMeasurementFixture();
    $otherTenant = Tenant::factory()->create(['has_esg_module' => true]);
    $otherTask = Task::factory()->create(['tenant_id' => $otherTenant->id]);

    $validator = Validator::make([
        'task_id' => $otherTask->id,
        'esg_indicator_id' => $fixture['indicator']->id,
        'recorded_at' => now()->toIso8601String(),
        'value_numeric' => 5,
    ], RecordEsgMeasurementRequest::ruleSet($fixture['tenant']->id));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('task_id'))->toBeTrue();
});

it('heeft geen update-action in de action-laag', function () {
    expect(class_exists(\App\Actions\Esg\UpdateEsgMeasurementAction::class))->toBeFalse();
});
