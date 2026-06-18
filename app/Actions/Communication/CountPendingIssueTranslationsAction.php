<?php

namespace App\Actions\Communication;

use App\Enums\IssueTranslationStatus;
use App\Models\IssueTranslation;

class CountPendingIssueTranslationsAction
{
    public function handle(): int
    {
        return IssueTranslation::query()
            ->where('status', IssueTranslationStatus::Pending)
            ->whereHas('issue', fn ($query) => $query->whereNotNull('approved_at'))
            ->count();
    }
}
