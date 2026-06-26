<?php

namespace App\Actions\Communication;

use App\Enums\IssueTranslationStatus;
use App\Models\IssueTranslation;

class ExportPendingIssueTranslationsAction
{
    /**
     * @return array{exported_at: string, items: list<array<string, mixed>>}
     */
    public function handle(): array
    {
        $items = IssueTranslation::query()
            ->where('status', IssueTranslationStatus::Pending)
            ->whereHas('issue', fn ($query) => $query
                ->whereNotNull('approved_at')
                ->where('description', '!=', ''))
            ->with('issue')
            ->orderBy('issue_id')
            ->orderBy('locale')
            ->get()
            ->map(function (IssueTranslation $row): array {
                $issue = $row->issue;

                return [
                    'issue_id' => $issue->id,
                    'tenant_id' => $issue->tenant_id,
                    'source_locale' => $issue->normalizedOriginalLanguage(),
                    'source_text' => (string) $issue->description,
                    'locale' => $row->locale,
                    'status' => IssueTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();

        return [
            'exported_at' => now()->toIso8601String(),
            'items' => $items,
        ];
    }
}
