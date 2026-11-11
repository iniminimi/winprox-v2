<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Unit */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'category_id' => $this->category_id,
            'name' => $this->localizedName(),
            'description' => $this->localizedDescription(),
            'original_language' => $this->normalizedOriginalLanguage(),
            'translations' => $this->whenLoaded(
                'translations',
                fn () => $this->completedTranslationMap(),
            ),
            'is_active' => (bool) $this->is_active,
            'external_id' => $this->external_id,
            'allow_unit_checks' => (bool) $this->allow_unit_checks,
        ];
    }
}
