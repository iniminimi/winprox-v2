<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Actions\Esg\CreateEsgThresholdFollowUpTaskAction;
use App\Enums\EsgIndicatorType;
use App\Events\Esg\EsgMeasurementRecorded;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\IotSensor;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Esg\EsgModuleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RecordIotEsgMeasurementAction
{
    public function __construct(
        private AuditRecorder $audit,
        private CreateEsgThresholdFollowUpTaskAction $createThresholdFollowUp,
    ) {}

    public function handle(
        IotSensor $sensor,
        float $value,
        CarbonImmutable $recordedAt,
        int $tenantId,
    ): EsgMeasurement {
        $tenant = Tenant::query()->find($tenantId);
        if (! EsgModuleAccess::tenantHasModule($tenant)) {
            throw ValidationException::withMessages([
                'kind' => [__('iot.errors.esg_required_for_measurement')],
            ]);
        }

        if ($sensor->esg_indicator_id === null || $sensor->unit_id === null) {
            throw ValidationException::withMessages([
                'external_sensor_id' => [__('iot.errors.sensor_not_measurement_ready')],
            ]);
        }

        $indicator = EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($sensor->esg_indicator_id);

        if ($indicator === null) {
            throw ValidationException::withMessages([
                'external_sensor_id' => [__('iot.errors.esg_indicator_invalid')],
            ]);
        }

        if ($indicator->type !== EsgIndicatorType::Numeric) {
            throw ValidationException::withMessages([
                'value' => [__('iot.errors.measurement_numeric_only')],
            ]);
        }

        $locationId = $sensor->location_id
            ?? $sensor->unit?->location_id;

        $measurement = EsgMeasurement::query()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $sensor->unit_id,
            'location_id' => $locationId,
            'task_id' => null,
            'esg_indicator_id' => $indicator->id,
            'worker_id' => null,
            'value_numeric' => round($value),
            'value_boolean' => null,
            'value_string' => null,
            'value_json' => null,
            'corrects_measurement_id' => null,
            'recorded_at' => $recordedAt,
            'created_at' => now(),
        ]);

        $this->audit->record(
            userId: null,
            tenantId: $tenantId,
            action: 'esg_measurement.recorded',
            modelType: EsgMeasurement::class,
            modelId: (int) $measurement->id,
            payload: [
                'id' => $measurement->id,
                'source' => 'iot',
                'iot_sensor_id' => $sensor->id,
                'esg_indicator_id' => $indicator->id,
                'unit_id' => $sensor->unit_id,
                'location_id' => $locationId,
                'value' => round($value),
                'recorded_at' => $recordedAt->toIso8601String(),
            ],
        );

        $measurement = $measurement->fresh(['indicator', 'unit', 'location']);

        event(new EsgMeasurementRecorded($measurement, null));

        $this->createThresholdFollowUp->handle($measurement, null);

        return $measurement;
    }
}
