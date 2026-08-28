<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Enums\DocumentTranslationStatus;
use App\Enums\EsgIndicatorTranslationStatus;
use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Enums\IssueTranslationStatus;
use App\Enums\CategoryTranslationStatus;
use App\Enums\InternalTeamTranslationStatus;
use App\Enums\LocationTranslationStatus;
use App\Enums\TaskTranslationStatus;
use App\Enums\UnitCheckListTranslationStatus;
use App\Enums\UnitTranslationStatus;
use App\Models\AnnouncementTranslation;
use App\Models\CategoryTranslation;
use App\Models\DocumentTranslation;
use App\Models\EsgIndicatorTranslation;
use App\Models\InternalTeamTranslation;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Models\IssueTranslation;
use App\Models\LocationTranslation;
use App\Models\TaskTranslation;
use App\Models\UnitCheckListTranslation;
use App\Models\UnitTranslation;

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

        $locations = LocationTranslation::query()
            ->where('status', LocationTranslationStatus::Pending)
            ->whereHas('location', fn ($query) => $query
                ->where('is_active', true)
                ->where('name', '!=', ''))
            ->count();

        $units = UnitTranslation::query()
            ->where('status', UnitTranslationStatus::Pending)
            ->whereHas('unit', fn ($query) => $query->where('is_active', true))
            ->count();

        $tasks = TaskTranslation::query()
            ->where('status', TaskTranslationStatus::Pending)
            ->whereHas('task', fn ($query) => $query->whereNotNull('description')->where('description', '!=', ''))
            ->count();

        $documents = DocumentTranslation::query()
            ->where('status', DocumentTranslationStatus::Pending)
            ->whereHas('document', fn ($query) => $query->where('is_active', true))
            ->count();

        $esgIndicators = EsgIndicatorTranslation::query()
            ->where('status', EsgIndicatorTranslationStatus::Pending)
            ->whereHas('indicator', fn ($query) => $query->where('is_active', true))
            ->count();

        $categories = CategoryTranslation::query()
            ->where('status', CategoryTranslationStatus::Pending)
            ->whereHas('category', fn ($query) => $query->where('name', '!=', ''))
            ->count();

        $teams = InternalTeamTranslation::query()
            ->where('status', InternalTeamTranslationStatus::Pending)
            ->whereHas('team', fn ($query) => $query->where('is_active', true)->where('name', '!=', ''))
            ->count();

        $unitCheckLists = UnitCheckListTranslation::query()
            ->where('status', UnitCheckListTranslationStatus::Pending)
            ->whereHas('list', fn ($query) => $query->where('is_active', true))
            ->count();

        $helpChatKb = HelpChatKnowledgeBaseEntryTranslation::query()
            ->where('status', HelpChatKnowledgeBaseEntryTranslationStatus::Pending)
            ->whereHas('entry', fn ($query) => $query->where('is_active', true))
            ->count();

        return $issues + $announcements + $locations + $units + $tasks + $documents + $esgIndicators + $categories + $teams + $unitCheckLists + $helpChatKb;
    }
}
