<?php

namespace App\Actions\Communication;

use App\Enums\LocationTranslationStatus;
use App\Models\LocationTranslation;

class ExportPendingLocationTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return LocationTranslation::query()
            ->where('status', LocationTranslationStatus::Pending)
            ->whereHas('location', fn ($query) => $query
                ->where('is_active', true)
                ->where('name', '!=', ''))
            ->with('location')
            ->orderBy('location_id')
            ->orderBy('locale')
            ->get()
            ->map(function (LocationTranslation $row): array {
                $location = $row->location;

                return [
                    'location_id' => $location->id,
                    'tenant_id' => $location->tenant_id,
                    'source_locale' => $location->normalizedOriginalLanguage(),
                    'source_name' => (string) $location->name,
                    'locale' => $row->locale,
                    'status' => LocationTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
