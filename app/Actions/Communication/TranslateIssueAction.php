<?php

namespace App\Actions\Communication;

use App\Enums\IssueTranslationStatus;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class TranslateIssueAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureIssueTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(Issue $issue, string $targetLocale, ?int $actorUserId = null): IssueTranslation
    {
        if (! $issue->isApproved()) {
            throw ValidationException::withMessages([
                'issue' => [__('issues.errors.translation_requires_approval')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);
        $sourceLocale = $issue->normalizedOriginalLanguage();

        if ($targetLocale === $sourceLocale) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($issue);

        $row = IssueTranslation::query()
            ->where('issue_id', $issue->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if (
            $row->status === IssueTranslationStatus::Completed
            && filled($row->description)
            && ! TranslationOutputGuard::isUntranslatedEcho(
                (string) $row->description,
                (string) $issue->description,
                $targetLocale,
                $sourceLocale,
            )
        ) {
            return $row;
        }

        $sourceText = trim((string) $issue->description);
        $translated = trim($this->translator->translate($sourceText, $targetLocale, $sourceLocale));

        if (
            $translated === ''
            || TranslationOutputGuard::isUnusable($translated, $sourceText)
            || TranslationOutputGuard::isUntranslatedEcho($translated, $sourceText, $targetLocale, $sourceLocale)
        ) {
            return $this->storeFailed($row, $issue, $targetLocale, $actorUserId, 'translation_empty_or_unusable');
        }

        if (mb_strlen($translated) > TextDescriptionLimits::TRANSLATION_MAX) {
            return $this->storeFailed($row, $issue, $targetLocale, $actorUserId, 'translation_too_long');
        }

        $row->fill([
            'description' => $translated,
            'status' => IssueTranslationStatus::Completed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $issue->tenant_id,
            'issue.translation_stored',
            IssueTranslation::class,
            (int) $row->id,
            [
                'issue_id' => $issue->id,
                'locale' => $targetLocale,
                'status' => IssueTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }

    private function storeFailed(
        IssueTranslation $row,
        Issue $issue,
        string $targetLocale,
        ?int $actorUserId,
        string $reason,
    ): IssueTranslation {
        $row->fill([
            'description' => null,
            'status' => IssueTranslationStatus::Failed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $issue->tenant_id,
            'issue.translation_stored',
            IssueTranslation::class,
            (int) $row->id,
            [
                'issue_id' => $issue->id,
                'locale' => $targetLocale,
                'status' => IssueTranslationStatus::Failed->value,
                'reason' => $reason,
            ],
        );

        return $row->fresh();
    }
}
