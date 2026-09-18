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
            'checklist_failed' => $this->checklist_failed,
            'description' => $this->description,
            'external_id' => $this->external_id,
            'google_maps_url' => $this->googleMapsUrl(),
            'photo_count' => $this->whenLoaded('photos', fn () => $this->photos->count(), 0),
            'photos' => $this->whenLoaded('photos', function () {
                return $this->photos
                    ->filter(fn ($photo) => $photo->hasPublicFile())
                    ->values()
                    ->map(fn ($photo) => [
                        'id' => $photo->id,
                        'url' => $photo->publicUrl(),
                    ]);
            }),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
