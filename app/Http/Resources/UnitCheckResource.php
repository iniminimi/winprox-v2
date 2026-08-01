<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UnitCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UnitCheck */
class UnitCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'location_id' => $this->location_id,
            'worker_id' => $this->worker_id,
            'internal_team_id' => $this->internal_team_id,
            'result' => $this->result->value,
            'source' => $this->source->value,
            'checked_at' => $this->checked_at->toIso8601String(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'task_id' => $this->task_id,
            'issue_id' => $this->issue_id,
            'checklist_items' => $this->checklist_items,
            'external_id' => $this->external_id,
            'google_maps_url' => $this->googleMapsUrl(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
