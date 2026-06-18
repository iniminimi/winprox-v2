<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Announcement */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'unit_id' => $this->unit_id,
            'description' => $this->localizedDescription(),
            'original_language' => $this->normalizedOriginalLanguage(),
            'translations' => $this->whenLoaded(
                'translations',
                fn () => $this->translations
                    ->filter(fn ($t) => $t->status->value === 'completed' && filled($t->description))
                    ->mapWithKeys(fn ($t) => [$t->locale => $t->description])
                    ->all(),
            ),
            'is_active' => $this->is_active,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
