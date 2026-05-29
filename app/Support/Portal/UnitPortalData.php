<?php

namespace App\Support\Portal;

use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Lees-queries voor het unit-QR portaal (houdt de Livewire-component dun).
 */
final class UnitPortalData
{
    /**
     * Open meldingen van deze unit (nog niet gesloten).
     *
     * @return Collection<int, Issue>
     */
    public static function activeIssuesForUnit(Unit $unit): Collection
    {
        return Issue::where('unit_id', $unit->id)
            ->where('status', '!=', TaskStatus::Closed->value)
            ->with('photos')
            ->latest()
            ->limit(20)
            ->get();
    }

    public static function findActiveIssueForUnit(Unit $unit, int $issueId): ?Issue
    {
        return Issue::where('unit_id', $unit->id)
            ->where('status', '!=', TaskStatus::Closed->value)
            ->with('photos')
            ->whereKey($issueId)
            ->first();
    }

    /**
     * @return Collection<int, Document>
     */
    public static function activeDocumentsForUnit(Unit $unit): Collection
    {
        return Document::where('location_id', $unit->location_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($q) => $q->where('unit_id', $unit->id)->orWhereNull('unit_id'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, Announcement>
     */
    public static function activeAnnouncementsForUnit(Unit $unit): Collection
    {
        return Announcement::where('location_id', $unit->location_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn ($q) => $q->where('unit_id', $unit->id)->orWhereNull('unit_id'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * Open taken (Nieuw/In uitvoering) voor een melding bij het standaardteam.
     *
     * @return Collection<int, Task>
     */
    public static function openTeamTasksForIssue(Issue $issue, int $teamId): Collection
    {
        return Task::where('issue_id', $issue->id)
            ->where('internal_team_id', $teamId)
            ->whereIn('status', TaskStatus::openValues())
            ->orderBy('id')
            ->get();
    }

    /**
     * Alle open taken van het standaardteam over alle (open) meldingen van de unit.
     *
     * @return Collection<int, Task>
     */
    public static function allOpenUnitTasks(Unit $unit, int $teamId): Collection
    {
        return Task::where('internal_team_id', $teamId)
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($q) => $q->where('unit_id', $unit->id))
            ->with(['issue.photos'])
            ->orderBy('id')
            ->get();
    }

    public static function hasOpenUnitTasks(Unit $unit, int $teamId): bool
    {
        return Task::where('internal_team_id', $teamId)
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($q) => $q->where('unit_id', $unit->id))
            ->exists();
    }
}
