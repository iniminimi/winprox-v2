<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Data\Iot\IngestIotEventData;
use App\Enums\IotEventKind;
use App\Enums\IotEventStatus;
use App\Models\IotEvent;
use App\Models\IotGateway;
use App\Models\IotRule;
use App\Models\IotSensor;
use App\Models\Tenant;
use App\Support\Iot\IotModuleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngestIotEventAction
{
    public function __construct(
        private CreateIotAlarmIssueAction $createAlarmIssue,
        private RecordIotEsgMeasurementAction $recordEsgMeasurement,
    ) {}

    public function handle(IotGateway $gateway, IngestIotEventData $data): IotEvent
    {
        $tenantId = (int) $gateway->tenant_id;
        $tenant = Tenant::query()->find($tenantId);

        if (! IotModuleAccess::tenantHasModule($tenant) || ! $gateway->is_active) {
            throw ValidationException::withMessages([
                'gateway' => [__('iot.errors.module_disabled')],
            ]);
        }

        if ($data->idempotencyKey !== null) {
            $existing = IotEvent::query()
                ->where('tenant_id', $tenantId)
                ->where('iot_gateway_id', $gateway->id)
                ->where('idempotency_key', $data->idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($gateway, $data, $tenantId, $tenant): IotEvent {
            $sensor = IotSensor::query()
                ->where('tenant_id', $tenantId)
                ->where('iot_gateway_id', $gateway->id)
                ->where('external_id', $data->externalSensorId)
                ->where('is_active', true)
                ->first();

            $event = IotEvent::query()->create([
                'tenant_id' => $tenantId,
                'iot_gateway_id' => $gateway->id,
                'iot_sensor_id' => $sensor?->id,
                'kind' => $data->kind,
                'external_sensor_id' => $data->externalSensorId,
                'value' => $data->value,
                'status' => IotEventStatus::Ignored,
                'idempotency_key' => $data->idempotencyKey,
                'raw_payload' => $data->rawPayload,
                'occurred_at' => $data->occurredAt,
                'received_at' => now(),
            ]);

            $gateway->forceFill(['last_seen_at' => now()])->save();

            if ($sensor === null) {
                $event->forceFill(['status' => IotEventStatus::Failed])->save();

                return $event->fresh();
            }

            if ($data->kind === IotEventKind::Measurement) {
                return $this->processMeasurement($event, $sensor, $data, $tenantId);
            }

            return $this->processAlarm($event, $sensor, $data);
        });
    }

    private function processMeasurement(
        IotEvent $event,
        IotSensor $sensor,
        IngestIotEventData $data,
        int $tenantId,
    ): IotEvent {
        if ($data->value === null) {
            $event->forceFill(['status' => IotEventStatus::Failed])->save();

            return $event->fresh();
        }

        try {
            $measurement = $this->recordEsgMeasurement->handle(
                $sensor,
                $data->value,
                $data->occurredAt,
                $tenantId,
            );
        } catch (ValidationException) {
            $event->forceFill(['status' => IotEventStatus::Ignored])->save();

            return $event->fresh();
        }

        $event->forceFill([
            'status' => IotEventStatus::Processed,
            'esg_measurement_id' => $measurement->id,
        ])->save();

        // Optional: also fire alarm rules when measurement breaches configured rules.
        $matchedRule = $this->firstMatchingRule($sensor, $data->value);
        if ($matchedRule !== null) {
            $issue = $this->createAlarmIssue->handle($matchedRule, $event, $data->value);
            if ($issue === null) {
                $event->forceFill([
                    'iot_rule_id' => $matchedRule->id,
                    'status' => IotEventStatus::Deduped,
                ])->save();
            } else {
                $event->forceFill([
                    'iot_rule_id' => $matchedRule->id,
                    'issue_id' => $issue->id,
                ])->save();
            }
        }

        return $event->fresh();
    }

    private function processAlarm(
        IotEvent $event,
        IotSensor $sensor,
        IngestIotEventData $data,
    ): IotEvent {
        $matchedRule = $this->firstMatchingRule($sensor, $data->value);

        if ($matchedRule === null) {
            $event->forceFill(['status' => IotEventStatus::Ignored])->save();

            return $event->fresh();
        }

        $issue = $this->createAlarmIssue->handle($matchedRule, $event, $data->value);

        if ($issue === null) {
            $event->forceFill([
                'iot_rule_id' => $matchedRule->id,
                'status' => IotEventStatus::Deduped,
            ])->save();

            return $event->fresh();
        }

        $event->forceFill([
            'iot_rule_id' => $matchedRule->id,
            'issue_id' => $issue->id,
            'status' => IotEventStatus::Processed,
        ])->save();

        return $event->fresh();
    }

    private function firstMatchingRule(IotSensor $sensor, ?float $value): ?IotRule
    {
        $rules = IotRule::query()
            ->where('iot_sensor_id', $sensor->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matchesValue($value)) {
                return $rule;
            }
        }

        return null;
    }
}
