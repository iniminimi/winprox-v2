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
        if (! $issue->isApproved()) {
            return;
        }

        foreach (LocaleSupport::targetLocalesFor($issue) as $locale) {
            IssueTranslation::firstOrCreate(
                [
                    'issue_id' => $issue->id,
                    'locale' => $locale,
                ],
                [
                    'status' => IssueTranslationStatus::Pending,
                ],
            );
        }
    }
}
