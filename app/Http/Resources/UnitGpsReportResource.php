<?php

namespace App\Http\Resources;

use App\Models\UnitGpsReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UnitGpsReport */
class UnitGpsReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_name' => $this->location_name,
            'country_code' => $this->country_code,
            'reported_at' => $this->reported_at->toIso8601String(),
            'worker_id' => $this->worker_id,
            'google_maps_url' => $this->googleMapsUrl(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
