<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UnitMeasurement */
class UnitMeasurementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('field');

        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'location_id' => $this->location_id,
            'unit_measure_field_id' => $this->unit_measure_field_id,
            'field_name' => $this->field?->name,
            'field_type' => $this->field?->type->value,
            'unit_of_measure' => $this->field?->unit_of_measure,
            'worker_id' => $this->worker_id,
            'user_id' => $this->user_id,
            'source' => $this->source->value,
            'value_numeric' => $this->value_numeric,
            'value_boolean' => $this->value_boolean,
            'value_string' => $this->value_string,
            'display_value' => $this->displayValue(),
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
