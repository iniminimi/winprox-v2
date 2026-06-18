<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Enums\IssueTranslationStatus;
use App\Models\AnnouncementTranslation;
use App\Models\IssueTranslation;

class CountPendingIssueTranslationsAction
{
    public function handle(): int
    {
        $issues = IssueTranslation::query()
            ->where('status', IssueTranslationStatus::Pending)
            ->whereHas('issue', fn ($query) => $query->whereNotNull('approved_at'))
            ->count();

        $announcements = AnnouncementTranslation::query()
            ->where('status', AnnouncementTranslationStatus::Pending)
            ->whereHas('announcement', fn ($query) => $query->where('is_active', true))
            ->count();

        return $issues + $announcements;
    }
}
