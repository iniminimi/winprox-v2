<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EsgMeasurement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EsgMeasurement */
class EsgMeasurementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'esg_indicator_id' => $this->esg_indicator_id,
            'unit_id' => $this->unit_id,
            'location_id' => $this->location_id,
            'worker_id' => $this->worker_id,
            'corrects_measurement_id' => $this->corrects_measurement_id,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'value_numeric' => $this->value_numeric !== null ? (int) round((float) $this->value_numeric) : null,
            'value_boolean' => $this->value_boolean,
            'value_string' => $this->value_string,
            'value_json' => $this->value_json,
        ];
    }
}
