<?php

declare(strict_types=1);

namespace App\Events\UnitMeasurements;

use App\Contracts\WebhookEvent;
use App\Models\UnitMeasurement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitMeasurementRecorded implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly UnitMeasurement $measurement,
        public readonly ?int $actorUserId = null,
    ) {}

    public function webhookEventName(): string
    {
        return 'unit.measurement.recorded';
    }

    public function webhookPayload(): array
    {
        $this->measurement->loadMissing(['field', 'unit']);

        $payload = [
            'id' => $this->measurement->id,
            'unit_id' => $this->measurement->unit_id,
            'location_id' => $this->measurement->location_id,
            'unit_measure_field_id' => $this->measurement->unit_measure_field_id,
            'field_name' => $this->measurement->field?->name,
            'field_type' => $this->measurement->field?->type->value,
            'unit_of_measure' => $this->measurement->field?->unit_of_measure,
            'worker_id' => $this->measurement->worker_id,
            'user_id' => $this->measurement->user_id,
            'source' => $this->measurement->source->value,
            'value_numeric' => $this->measurement->value_numeric,
            'value_boolean' => $this->measurement->value_boolean,
            'value_string' => $this->measurement->value_string,
            'display_value' => $this->measurement->displayValue(),
            'recorded_at' => $this->measurement->recorded_at?->toIso8601String(),
            'unit_external_id' => $this->measurement->unit?->external_id,
        ];

        if ($this->actorUserId !== null) {
            $payload['actor_user_id'] = $this->actorUserId;
        }

        return $payload;
    }

    public function webhookTenantId(): int
    {
        return (int) $this->measurement->tenant_id;
    }
}
