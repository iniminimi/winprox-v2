<?php

namespace App\Actions\Communication;

use App\Enums\IssueTranslationStatus;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
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

        if ($targetLocale === $issue->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($issue);

        $row = IssueTranslation::query()
            ->where('issue_id', $issue->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === IssueTranslationStatus::Completed && filled($row->text)) {
            return $row;
        }

        $sourceText = trim((string) $issue->description);
        $translated = $this->translator->translate($sourceText, $targetLocale);
        $status = ($translated !== '' && $translated !== $sourceText)
            ? IssueTranslationStatus::Completed
            : IssueTranslationStatus::Failed;

        $row->fill([
            'text' => $translated !== '' ? $translated : $sourceText,
            'status' => $status,
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
                'status' => $status->value,
            ],
        );

        return $row->fresh();
    }
}
