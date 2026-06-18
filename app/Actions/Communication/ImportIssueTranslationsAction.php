<?php

namespace App\Actions\Communication;

use App\Enums\IssueTranslationStatus;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Validation\ValidationException;

class ImportIssueTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureIssueTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $issueId = (int) ($item['issue_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));

            if ($issueId <= 0 || $locale === '' || $text === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            $issue = Issue::query()->find($issueId);

            if ($issue === null || ! $issue->isApproved()) {
                throw ValidationException::withMessages([
                    "items.{$index}.issue_id" => [__('issues.errors.translation_import_issue_missing')],
                ]);
            }

            if ($locale === $issue->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($issue);

            $row = IssueTranslation::query()
                ->where('issue_id', $issue->id)
                ->where('locale', $locale)
                ->firstOrFail();

            $row->fill([
                'text' => $text,
                'status' => IssueTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $issue->tenant_id,
                'issue.translation_imported',
                IssueTranslation::class,
                (int) $row->id,
                [
                    'issue_id' => $issue->id,
                    'locale' => $locale,
                ],
            );

            $imported++;
        }

        return $imported;
    }
}
