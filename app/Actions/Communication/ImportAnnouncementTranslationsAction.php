<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Events\Communication\AnnouncementTranslationImported;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class ImportAnnouncementTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureAnnouncementTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $announcementId = (int) ($item['announcement_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $description = trim((string) ($item['description'] ?? $item['text'] ?? ''));

            if ($announcementId <= 0 || $locale === '' || $description === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (mb_strlen($description) > TextDescriptionLimits::TRANSLATION_MAX) {
                throw ValidationException::withMessages([
                    "items.{$index}.description" => [__('issues.errors.translation_import_too_long')],
                ]);
            }

            $announcement = Announcement::query()->find($announcementId);

            if ($announcement === null || ! $announcement->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.announcement_id" => [__('locations.announcements.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $announcement->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($announcement);

            $row = AnnouncementTranslation::query()
                ->where('announcement_id', $announcement->id)
                ->where('locale', $locale)
                ->firstOrFail();

            if (
                $row->status === AnnouncementTranslationStatus::Completed
                && $row->description === $description
            ) {
                continue;
            }

            $row->fill([
                'description' => $description,
                'status' => AnnouncementTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $announcement->tenant_id,
                'announcement.translation_imported',
                AnnouncementTranslation::class,
                (int) $row->id,
                [
                    'announcement_id' => $announcement->id,
                    'locale' => $locale,
                ],
            );

            AnnouncementTranslationImported::dispatch($row, $actorUserId);

            $imported++;
        }

        return $imported;
    }
}
