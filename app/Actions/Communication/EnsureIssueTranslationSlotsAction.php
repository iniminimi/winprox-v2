<?php

namespace App\Actions\Communication;

use App\Enums\IssueTranslationStatus;
use App\Models\Issue;
use App\Support\Translation\LocaleSupport;
use App\Models\IssueTranslation;

class EnsureIssueTranslationSlotsAction
{
    public function handle(Issue $issue): void
    {
        if (! $issue->isApproved() || trim((string) $issue->description) === '') {
            return;
        }

        foreach (LocaleSupport::targetLocalesFor($issue) as $locale) {
            $row = IssueTranslation::firstOrCreate(
                [
                    'issue_id' => $issue->id,
                    'locale' => $locale,
                ],
                [
                    'status' => IssueTranslationStatus::Pending,
                ],
            );

            // Self-heal failed rows so local translate runs can retry.
            if (
                $row->status === IssueTranslationStatus::Failed
                && blank($row->description)
            ) {
                $row->fill([
                    'status' => IssueTranslationStatus::Pending,
                ])->save();
            }
        }
    }
}
