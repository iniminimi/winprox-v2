<?php

namespace App\Actions\Communication;

use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureEsgIndicatorTranslationSlotsAction
{
    public function handle(EsgIndicator $indicator): void
    {
        if (! $indicator->is_active) {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($indicator->original_language) as $locale) {
            EsgIndicatorTranslation::firstOrCreate(
                [
                    'esg_indicator_id' => $indicator->id,
                    'locale' => $locale,
                ],
                [
                    'status' => EsgIndicatorTranslationStatus::Pending,
                ],
            );
        }
    }
}
