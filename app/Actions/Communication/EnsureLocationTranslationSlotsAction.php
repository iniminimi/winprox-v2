<?php

namespace App\Actions\Communication;

use App\Enums\LocationTranslationStatus;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureLocationTranslationSlotsAction
{
    public function handle(Location $location): void
    {
        if (! $location->is_active || trim((string) $location->name) === '') {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($location->original_language) as $locale) {
            LocationTranslation::firstOrCreate(
                [
                    'location_id' => $location->id,
                    'locale' => $locale,
                ],
                [
                    'status' => LocationTranslationStatus::Pending,
                ],
            );
        }
    }
}
