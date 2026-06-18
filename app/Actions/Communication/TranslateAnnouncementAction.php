<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
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

        if ($targetLocale === $announcement->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($announcement);

        $row = AnnouncementTranslation::query()
            ->where('announcement_id', $announcement->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === AnnouncementTranslationStatus::Completed && filled($row->description)) {
            return $row;
        }

        $sourceText = trim((string) $announcement->description);
        $translated = trim($this->translator->translate($sourceText, $targetLocale));
        $stored = $translated !== '' ? $translated : $sourceText;

        if (mb_strlen($stored) > TextDescriptionLimits::TRANSLATION_MAX) {
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
                    'reason' => 'translation_too_long',
                ],
            );

            return $row->fresh();
        }

        $status = ($translated !== '' && $translated !== $sourceText)
            ? AnnouncementTranslationStatus::Completed
            : AnnouncementTranslationStatus::Failed;

        $row->fill([
            'description' => $stored,
            'status' => $status,
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
                'status' => $status->value,
            ],
        );

        return $row->fresh();
    }
}
