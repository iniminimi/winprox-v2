<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Enums\IotSensorType;
use App\Models\EsgIndicator;
use App\Models\IotGateway;
use App\Models\IotSensor;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Esg\EsgModuleAccess;
use Illuminate\Validation\ValidationException;

class UpdateIotSensorAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{
     *     iot_gateway_id: int,
     *     external_id: string,
     *     name: string,
     *     sensor_type: string,
     *     location_id?: int|null,
     *     unit_id?: int|null,
     *     esg_indicator_id?: int|null
     * }  $data
     */
    public function handle(IotSensor $sensor, array $data, int $tenantId, ?int $actorUserId = null): IotSensor
    {
        $gateway = IotGateway::query()
            ->where('tenant_id', $tenantId)
            ->find($data['iot_gateway_id']);

        if ($gateway === null) {
            throw ValidationException::withMessages([
                'iot_gateway_id' => [__('iot.errors.gateway_invalid')],
            ]);
        }

        $externalId = (string) $data['external_id'];
        $duplicate = IotSensor::query()
            ->where('tenant_id', $tenantId)
            ->where('iot_gateway_id', $gateway->id)
            ->where('external_id', $externalId)
            ->whereKeyNot($sensor->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'external_id' => [__('iot.errors.external_id_taken')],
            ]);
        }

        $locationId = filled($data['location_id'] ?? null) ? (int) $data['location_id'] : null;
        $unitId = filled($data['unit_id'] ?? null) ? (int) $data['unit_id'] : null;
        $esgIndicatorId = filled($data['esg_indicator_id'] ?? null) ? (int) $data['esg_indicator_id'] : null;

        if ($locationId !== null) {
            $locationExists = Location::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($locationId)
                ->exists();
            if (! $locationExists) {
                throw ValidationException::withMessages([
                    'location_id' => [__('iot.errors.location_invalid')],
                ]);
            }
        }

        if ($unitId !== null) {
            $unit = Unit::query()
                ->where('tenant_id', $tenantId)
                ->find($unitId);
            if ($unit === null) {
                throw ValidationException::withMessages([
                    'unit_id' => [__('iot.errors.unit_invalid')],
                ]);
            }
            if ($locationId === null) {
                $locationId = $unit->location_id !== null ? (int) $unit->location_id : null;
            }
        }

        if ($esgIndicatorId !== null) {
            $tenant = Tenant::query()->find($tenantId);
            if (! EsgModuleAccess::tenantHasModule($tenant)) {
                throw ValidationException::withMessages([
                    'esg_indicator_id' => [__('iot.errors.esg_required_for_indicator')],
                ]);
            }

            $indicatorExists = EsgIndicator::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereKey($esgIndicatorId)
                ->exists();

            if (! $indicatorExists) {
                throw ValidationException::withMessages([
                    'esg_indicator_id' => [__('iot.errors.esg_indicator_invalid')],
                ]);
            }
        }

        $sensor->forceFill([
            'iot_gateway_id' => $gateway->id,
            'external_id' => $externalId,
            'name' => (string) $data['name'],
            'sensor_type' => IotSensorType::from((string) $data['sensor_type']),
            'location_id' => $locationId,
            'unit_id' => $unitId,
            'esg_indicator_id' => $esgIndicatorId,
        ])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'iot_sensor.updated',
            modelType: IotSensor::class,
            modelId: (int) $sensor->id,
            payload: [
                'id' => $sensor->id,
                'iot_gateway_id' => $gateway->id,
                'external_id' => $externalId,
                'sensor_type' => $sensor->sensor_type->value,
                'location_id' => $locationId,
                'unit_id' => $unitId,
                'esg_indicator_id' => $esgIndicatorId,
            ],
        );

        return $sensor->fresh();
    }
}
