<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Models\AnnouncementTranslation;

class ExportPendingAnnouncementTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return AnnouncementTranslation::query()
            ->where('status', AnnouncementTranslationStatus::Pending)
            ->whereHas('announcement', fn ($query) => $query->where('is_active', true))
            ->with('announcement')
            ->orderBy('announcement_id')
            ->orderBy('locale')
            ->get()
            ->map(function (AnnouncementTranslation $row): array {
                $announcement = $row->announcement;

                return [
                    'announcement_id' => $announcement->id,
                    'tenant_id' => $announcement->tenant_id,
                    'source_locale' => $announcement->normalizedOriginalLanguage(),
                    'source_text' => (string) $announcement->description,
                    'locale' => $row->locale,
                    'status' => AnnouncementTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
