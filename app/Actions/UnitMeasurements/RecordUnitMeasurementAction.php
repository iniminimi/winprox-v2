<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Data\UnitMeasurements\RecordUnitMeasurementData;
use App\Enums\UnitMeasureFieldType;
use App\Events\UnitMeasurements\UnitMeasurementRecorded;
use App\Models\Unit;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class RecordUnitMeasurementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        Unit $unit,
        RecordUnitMeasurementData $data,
        int $tenantId,
        ?Worker $worker = null,
        ?int $actorUserId = null,
    ): UnitMeasurement {
        if ((int) $unit->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'unit_id' => [__('unit_measurements.errors.unit_invalid')],
            ]);
        }

        if (! $unit->allowsUnitMeasurements()) {
            throw ValidationException::withMessages([
                'unit_id' => [__('unit_measurements.errors.unit_not_enabled')],
            ]);
        }

        $field = UnitMeasureField::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($data->unitMeasureFieldId);

        if ($field === null) {
            throw ValidationException::withMessages([
                'unit_measure_field_id' => [__('unit_measurements.errors.field_invalid')],
            ]);
        }

        $linked = $unit->measureFields()
            ->where('unit_measure_fields.id', $field->id)
            ->exists();

        if (! $linked) {
            throw ValidationException::withMessages([
                'unit_measure_field_id' => [__('unit_measurements.errors.field_not_linked')],
            ]);
        }

        if ($data->valueForType($field->type) === null) {
            throw ValidationException::withMessages([
                $field->type->valueColumn() => [__('unit_measurements.errors.value_required')],
            ]);
        }

        $this->assertValueMatchesField($field, $data);

        if ($worker !== null && (int) $worker->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'worker_id' => [__('unit_measurements.errors.worker_invalid')],
            ]);
        }

        $unit->loadMissing('location');

        $measurement = UnitMeasurement::query()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unit->id,
            'location_id' => $unit->location_id,
            'unit_measure_field_id' => $field->id,
            'worker_id' => $worker?->id,
            'user_id' => $actorUserId,
            'source' => $data->source,
            ...$data->valueColumnsForInsert($field->type),
            'recorded_at' => $data->recordedAt,
            'created_at' => now(),
        ]);

        $measurement = $measurement->fresh(['field', 'unit', 'location', 'worker']);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit_measurement.recorded',
            modelType: UnitMeasurement::class,
            modelId: (int) $measurement->id,
            payload: [
                'id' => $measurement->id,
                'unit_id' => $measurement->unit_id,
                'unit_measure_field_id' => $measurement->unit_measure_field_id,
                'source' => $measurement->source->value,
            ],
        );

        event(new UnitMeasurementRecorded($measurement, $actorUserId));

        return $measurement;
    }

    private function assertValueMatchesField(UnitMeasureField $field, RecordUnitMeasurementData $data): void
    {
        if ($field->type === UnitMeasureFieldType::Numeric) {
            $value = $data->valueNumeric;
            if ($value === null) {
                return;
            }
            if ($field->min_value !== null && $value < (float) $field->min_value) {
                $this->failFieldValue(
                    $field,
                    'value_numeric',
                    __('unit_measurements.errors.value_below_min', ['min' => $field->min_value]),
                );
            }
            if ($field->max_value !== null && $value > (float) $field->max_value) {
                $this->failFieldValue(
                    $field,
                    'value_numeric',
                    __('unit_measurements.errors.value_above_max', ['max' => $field->max_value]),
                );
            }
        }

        if ($field->type === UnitMeasureFieldType::Choice) {
            $options = $field->normalizedChoiceOptions();
            if ($data->valueString === null || ! in_array($data->valueString, $options, true)) {
                $this->failFieldValue($field, 'value_string', __('unit_measurements.errors.value_choice_invalid'));
            }
        }

        if ($field->type === UnitMeasureFieldType::String && $data->valueString !== null && mb_strlen($data->valueString) > 500) {
            $this->failFieldValue($field, 'value_string', __('unit_measurements.errors.value_string_too_long'));
        }
    }

    private function failFieldValue(UnitMeasureField $field, string $valueKey, string $message): never
    {
        throw ValidationException::withMessages([
            $valueKey => [$message],
            'fields.'.$field->id => [$message],
        ]);
    }
}
