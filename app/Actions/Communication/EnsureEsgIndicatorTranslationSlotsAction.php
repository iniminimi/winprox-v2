<?php

namespace App\Actions\Communication;

use App\Models\Tenant;
use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureEsgIndicatorTranslationSlotsAction
{
    public function handle(EsgIndicator $indicator): void
    {
        if (! Tenant::translationsEnabledFor((int) $indicator->tenant_id)) {
            return;
        }

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
