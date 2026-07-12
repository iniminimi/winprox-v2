<?php

declare(strict_types=1);

namespace App\Events\Esg;

use App\Contracts\WebhookEvent;
use App\Models\EsgMeasurement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EsgMeasurementRecorded implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly EsgMeasurement $measurement,
        public readonly ?int $actorUserId = null,
    ) {
    }

    public function webhookEventName(): string
    {
        return 'esg.measurement.recorded';
    }

    public function webhookPayload(): array
    {
        $measurement = $this->measurement;
        $indicator = $measurement->relationLoaded('indicator') ? $measurement->indicator : null;

        $payload = [
            'id' => $measurement->id,
            'task_id' => $measurement->task_id,
            'esg_indicator_id' => $measurement->esg_indicator_id,
            'unit_id' => $measurement->unit_id,
            'location_id' => $measurement->location_id,
            'worker_id' => $measurement->worker_id,
            'corrects_measurement_id' => $measurement->corrects_measurement_id,
            'recorded_at' => $measurement->recorded_at?->toIso8601String(),
            'created_at' => $measurement->created_at?->toIso8601String(),
            'indicator_type' => $indicator?->type->value,
            'value_numeric' => $measurement->value_numeric !== null ? (int) round((float) $measurement->value_numeric) : null,
            'value_boolean' => $measurement->value_boolean,
            'value_string' => $measurement->value_string,
            'value_json' => $measurement->value_json,
        ];

        if ($this->actorUserId !== null) {
            $payload['actor_user_id'] = $this->actorUserId;
            $payload['user_id'] = $this->actorUserId;
        }

        return $payload;
    }

    public function webhookTenantId(): int
    {
        return (int) $this->measurement->tenant_id;
    }
}
