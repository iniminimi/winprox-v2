<?php

namespace App\Actions\Communication;

use App\Models\Tenant;
use App\Enums\AnnouncementTranslationStatus;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureAnnouncementTranslationSlotsAction
{
    public function handle(Announcement $announcement): void
    {
        if (! Tenant::translationsEnabledFor((int) $announcement->tenant_id)) {
            return;
        }

        if (! $announcement->is_active || trim((string) $announcement->description) === '') {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($announcement->original_language) as $locale) {
            AnnouncementTranslation::firstOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'locale' => $locale,
                ],
                [
                    'status' => AnnouncementTranslationStatus::Pending,
                ],
            );
        }
    }
}
