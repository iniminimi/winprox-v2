<?php

namespace App\Actions\Communication;

use App\Models\Tenant;
use App\Enums\UnitTranslationStatus;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureUnitTranslationSlotsAction
{
    public function handle(Unit $unit): void
    {
        if (! Tenant::translationsEnabledFor((int) $unit->tenant_id)) {
            return;
        }

        if (! $unit->is_active) {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($unit->original_language) as $locale) {
            UnitTranslation::firstOrCreate(
                [
                    'unit_id' => $unit->id,
                    'locale' => $locale,
                ],
                [
                    'status' => UnitTranslationStatus::Pending,
                ],
            );
        }
    }
}
