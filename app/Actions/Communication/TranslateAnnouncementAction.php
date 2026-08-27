<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class TranslateAnnouncementAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureAnnouncementTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(Announcement $announcement, string $targetLocale, ?int $actorUserId = null): AnnouncementTranslation
    {
        if (! $announcement->is_active) {
            throw ValidationException::withMessages([
                'announcement' => [__('locations.announcements.errors.translation_requires_active')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);
        $sourceLocale = $announcement->normalizedOriginalLanguage();

        if ($targetLocale === $sourceLocale) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($announcement);

        $row = AnnouncementTranslation::query()
            ->where('announcement_id', $announcement->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if (
            $row->status === AnnouncementTranslationStatus::Completed
            && filled($row->description)
            && ! TranslationOutputGuard::isUntranslatedEcho(
                (string) $row->description,
                (string) $announcement->description,
                $targetLocale,
                $sourceLocale,
            )
        ) {
            return $row;
        }

        $sourceText = trim((string) $announcement->description);
        $translated = trim($this->translator->translate($sourceText, $targetLocale, $sourceLocale));

        if (
            $translated === ''
            || TranslationOutputGuard::isUnusable($translated, $sourceText)
            || TranslationOutputGuard::isUntranslatedEcho($translated, $sourceText, $targetLocale, $sourceLocale)
        ) {
            return $this->storeFailed($row, $announcement, $targetLocale, $actorUserId, 'translation_empty_or_unusable');
        }

        if (mb_strlen($translated) > TextDescriptionLimits::TRANSLATION_MAX) {
            return $this->storeFailed($row, $announcement, $targetLocale, $actorUserId, 'translation_too_long');
        }

        $row->fill([
            'description' => $translated,
            'status' => AnnouncementTranslationStatus::Completed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $announcement->tenant_id,
            'announcement.translation_stored',
            AnnouncementTranslation::class,
            (int) $row->id,
            [
                'announcement_id' => $announcement->id,
                'locale' => $targetLocale,
                'status' => AnnouncementTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }

    private function storeFailed(
        AnnouncementTranslation $row,
        Announcement $announcement,
        string $targetLocale,
        ?int $actorUserId,
        string $reason,
    ): AnnouncementTranslation {
        $row->fill([
            'description' => null,
            'status' => AnnouncementTranslationStatus::Failed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $announcement->tenant_id,
            'announcement.translation_stored',
            AnnouncementTranslation::class,
            (int) $row->id,
            [
                'announcement_id' => $announcement->id,
                'locale' => $targetLocale,
                'status' => AnnouncementTranslationStatus::Failed->value,
                'reason' => $reason,
            ],
        );

        return $row->fresh();
    }
}
