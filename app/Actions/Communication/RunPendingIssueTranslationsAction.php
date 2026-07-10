<?php

namespace App\Actions\Communication;

use App\Enums\IssueTranslationStatus;
use App\Models\IssueTranslation;

class RunPendingIssueTranslationsAction
{
    public function __construct(private TranslateIssueAction $translateIssue) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = IssueTranslation::query()
            ->where('status', IssueTranslationStatus::Pending)
            ->whereHas('issue', fn ($query) => $query->whereNotNull('approved_at'))
            ->with('issue')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->issue === null) {
                continue;
            }

            $this->translateIssue->handle($row->issue, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
