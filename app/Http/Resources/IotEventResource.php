<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\IotEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IotEvent */
class IotEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'external_sensor_id' => $this->external_sensor_id,
            'value' => $this->value !== null ? (float) $this->value : null,
            'iot_sensor_id' => $this->iot_sensor_id,
            'iot_rule_id' => $this->iot_rule_id,
            'issue_id' => $this->issue_id,
            'esg_measurement_id' => $this->esg_measurement_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}
