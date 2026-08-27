<?php

declare(strict_types=1);

use App\Actions\UnitMeasurements\RecordUnitMeasurementAction;
use App\Data\UnitMeasurements\RecordUnitMeasurementData;
use App\Enums\UnitMeasurementSource;
use App\Events\UnitMeasurements\UnitMeasurementRecorded;
use App\Models\Category;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     location: Location,
 *     category: Category,
 *     unit: Unit,
 *     field: UnitMeasureField
 * }
 */
function unitMeasurementFixture(array $fieldOverrides = [], array $unitOverrides = []): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_measurements' => true,
    ]);
    $unit = Unit::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'allow_unit_measurements' => true,
    ], $unitOverrides));
    $field = UnitMeasureField::factory()->numeric('km')->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Kilometerstand',
    ], $fieldOverrides));
    $unit->measureFields()->sync([$field->id]);

    return compact('tenant', 'location', 'category', 'unit', 'field');
}

it('records a numeric unit measurement append-only', function () {
    Event::fake([UnitMeasurementRecorded::class]);
    $fixture = unitMeasurementFixture();
    $recordedAt = CarbonImmutable::parse('2026-08-27 10:15:00');

    $measurement = app(RecordUnitMeasurementAction::class)->handle(
        unit: $fixture['unit'],
        data: new RecordUnitMeasurementData(
            unitMeasureFieldId: (int) $fixture['field']->id,
            source: UnitMeasurementSource::Admin,
            recordedAt: $recordedAt,
            valueNumeric: 125430.5,
        ),
        tenantId: (int) $fixture['tenant']->id,
    );

    expect(UnitMeasurement::query()->whereKey($measurement->id)->exists())->toBeTrue()
        ->and($measurement->tenant_id)->toBe($fixture['tenant']->id)
        ->and($measurement->unit_id)->toBe($fixture['unit']->id)
        ->and($measurement->location_id)->toBe($fixture['location']->id)
        ->and($measurement->unit_measure_field_id)->toBe($fixture['field']->id)
        ->and((float) $measurement->value_numeric)->toBe(125430.5)
        ->and($measurement->source)->toBe(UnitMeasurementSource::Admin);

    Event::assertDispatched(UnitMeasurementRecorded::class);
});

it('rejects measurement when unit measurements are disabled', function () {
    $fixture = unitMeasurementFixture(unitOverrides: ['allow_unit_measurements' => false]);

    expect(fn () => app(RecordUnitMeasurementAction::class)->handle(
        unit: $fixture['unit']->fresh(),
        data: new RecordUnitMeasurementData(
            unitMeasureFieldId: (int) $fixture['field']->id,
            source: UnitMeasurementSource::Api,
            recordedAt: CarbonImmutable::now(),
            valueNumeric: 10,
        ),
        tenantId: (int) $fixture['tenant']->id,
    ))->toThrow(ValidationException::class);
});

it('rejects numeric values above the field maximum', function () {
    $fixture = unitMeasurementFixture(['max_value' => 999999]);

    try {
        app(RecordUnitMeasurementAction::class)->handle(
            unit: $fixture['unit'],
            data: new RecordUnitMeasurementData(
                unitMeasureFieldId: (int) $fixture['field']->id,
                source: UnitMeasurementSource::Portal,
                recordedAt: CarbonImmutable::now(),
                valueNumeric: 5000000,
            ),
            tenantId: (int) $fixture['tenant']->id,
        );
        expect(false)->toBeTrue();
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('value_numeric')
            ->and($e->errors())->toHaveKey('fields.'.$fixture['field']->id);
    }
});

it('dispatches unit.measurement.recorded webhook', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('ok', 200)]);

    $fixture = unitMeasurementFixture();

    WebhookEndpoint::factory()->create([
        'tenant_id' => $fixture['tenant']->id,
        'events' => ['unit.measurement.recorded'],
        'is_active' => true,
    ]);

    app(RecordUnitMeasurementAction::class)->handle(
        unit: $fixture['unit'],
        data: new RecordUnitMeasurementData(
            unitMeasureFieldId: (int) $fixture['field']->id,
            source: UnitMeasurementSource::Api,
            recordedAt: CarbonImmutable::now(),
            valueNumeric: 42,
        ),
        tenantId: (int) $fixture['tenant']->id,
    );

    $this->assertDatabaseHas('webhook_deliveries', [
        'tenant_id' => $fixture['tenant']->id,
        'event' => 'unit.measurement.recorded',
    ]);
});

it('rejects measurement when field is not linked to the unit', function () {
    $fixture = unitMeasurementFixture();
    $other = UnitMeasureField::factory()->numeric('L')->create([
        'tenant_id' => $fixture['tenant']->id,
        'name' => 'Brandstof',
    ]);

    expect(fn () => app(RecordUnitMeasurementAction::class)->handle(
        unit: $fixture['unit'],
        data: new RecordUnitMeasurementData(
            unitMeasureFieldId: (int) $other->id,
            source: UnitMeasurementSource::Api,
            recordedAt: CarbonImmutable::now(),
            valueNumeric: 40,
        ),
        tenantId: (int) $fixture['tenant']->id,
    ))->toThrow(ValidationException::class);
});

it('supports boolean and choice fields', function () {
    $fixture = unitMeasurementFixture();
    $boolean = UnitMeasureField::factory()->boolean()->create([
        'tenant_id' => $fixture['tenant']->id,
        'name' => 'In werking',
    ]);
    $choice = UnitMeasureField::factory()->choice(['OK', 'Defect'])->create([
        'tenant_id' => $fixture['tenant']->id,
        'name' => 'Status',
    ]);
    $fixture['unit']->measureFields()->sync([
        $fixture['field']->id,
        $boolean->id,
        $choice->id,
    ]);

    $boolMeasurement = app(RecordUnitMeasurementAction::class)->handle(
        unit: $fixture['unit'],
        data: new RecordUnitMeasurementData(
            unitMeasureFieldId: (int) $boolean->id,
            source: UnitMeasurementSource::Portal,
            recordedAt: CarbonImmutable::now(),
            valueBoolean: true,
        ),
        tenantId: (int) $fixture['tenant']->id,
    );
    $choiceMeasurement = app(RecordUnitMeasurementAction::class)->handle(
        unit: $fixture['unit'],
        data: new RecordUnitMeasurementData(
            unitMeasureFieldId: (int) $choice->id,
            source: UnitMeasurementSource::Portal,
            recordedAt: CarbonImmutable::now(),
            valueString: 'OK',
        ),
        tenantId: (int) $fixture['tenant']->id,
    );

    expect($boolMeasurement->value_boolean)->toBeTrue()
        ->and($choiceMeasurement->value_string)->toBe('OK');
});
