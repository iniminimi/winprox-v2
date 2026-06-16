<?php

namespace App\Support\Portal;

use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
            ->with([
                'photos',
                'updates' => fn ($q) => $q->latest(),
                'updates.photos',
                'updates.user:id,name',
                'updates.worker:id,first_name,last_name',
            ])
            ->whereKey($issueId)
            ->first();
    }

    /**
     * @return Collection<int, Document>
     */
    public static function activeDocumentsForUnit(Unit $unit): Collection
    {
        $query = Document::where('location_id', $unit->location_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20);

        if (! Schema::hasColumn('documents', 'category_id')) {
            return $query
                ->where(fn ($q) => $q->where('unit_id', $unit->id)->orWhereNull('unit_id'))
                ->get();
        }

        return $query
            ->where(function ($q) use ($unit) {
                $q->where('unit_id', $unit->id)
                    ->orWhere(function ($scoped) use ($unit) {
                        $scoped->whereNull('unit_id')
                            ->where(function ($categoryScope) use ($unit) {
                                $categoryScope->whereNull('category_id');

                                if ($unit->category_id !== null) {
                                    $categoryScope->orWhere('category_id', $unit->category_id);
                                }
                            });
                    });
            })
            ->get();
    }

    /**
     * @return Collection<int, Announcement>
     */
    public static function activeAnnouncementsForUnit(Unit $unit): Collection
    {
        $query = Announcement::where('location_id', $unit->location_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20);

        if (! Schema::hasColumn('announcements', 'category_id')) {
            return $query
                ->where(fn ($q) => $q->where('unit_id', $unit->id)->orWhereNull('unit_id'))
                ->get();
        }

        return $query
            ->where(function ($q) use ($unit) {
                $q->where('unit_id', $unit->id)
                    ->orWhere(function ($scoped) use ($unit) {
                        $scoped->whereNull('unit_id')
                            ->where(function ($categoryScope) use ($unit) {
                                $categoryScope->whereNull('category_id');

                                if ($unit->category_id !== null) {
                                    $categoryScope->orWhere('category_id', $unit->category_id);
                                }
                            });
                    });
            })
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
            ->whereHas('issue', fn ($q) => $q->whereNotNull('approved_at'))
            ->with(['issue.photos', 'issue.updates'])
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
            ->whereHas('issue', fn ($q) => $q
                ->where('unit_id', $unit->id)
                ->whereNotNull('approved_at'))
            ->with(['issue.photos', 'issue.updates'])
            ->orderBy('id')
            ->get();
    }

    public static function hasOpenUnitTasks(Unit $unit, int $teamId): bool
    {
        return self::openUnitTaskCount($unit, $teamId) > 0;
    }

    public static function openUnitTaskCount(Unit $unit, int $teamId): int
    {
        return Task::where('internal_team_id', $teamId)
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($q) => $q
                ->where('unit_id', $unit->id)
                ->whereNotNull('approved_at'))
            ->count();
    }
}
