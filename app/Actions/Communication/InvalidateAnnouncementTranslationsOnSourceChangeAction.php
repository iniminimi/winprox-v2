<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateAnnouncementTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Announcement $announcement, string $previousDescription, ?int $actorUserId = null): void
    {
        if (trim($previousDescription) === trim((string) $announcement->description)) {
            return;
        }

        if (! $announcement->is_active) {
            return;
        }

        $source = $announcement->normalizedOriginalLanguage();

        $invalidated = AnnouncementTranslation::query()
            ->where('announcement_id', $announcement->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', AnnouncementTranslationStatus::Pending->value)
                    ->orWhereNotNull('description');
            })
            ->update([
                'description' => null,
                'status' => AnnouncementTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $announcement->tenant_id,
            'announcement.translations_invalidated',
            Announcement::class,
            (int) $announcement->id,
            [
                'announcement_id' => $announcement->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
