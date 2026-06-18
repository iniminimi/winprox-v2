<?php

namespace App\Actions\Briefing;

use App\Data\Briefing\BriefingLineData;
use App\Data\Briefing\MorningBriefingViewData;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BuildMorningBriefingAction
{
    public function handle(
        Tenant $tenant,
        ?User $actor,
        ?int $teamId,
        ?Carbon $date = null,
        bool $openTasksOnly = false,
    ): MorningBriefingViewData {
        $teams = $this->teamsForTenant($tenant);
        $date = ($date ?? now())->copy()->startOfDay();
        $team = $this->resolveTeam($teams, $teamId);

        $unitLines = collect();
        $generalLines = collect();

        if ($team !== null) {
            $dayEnd = $date->copy()->endOfDay();

            foreach ($this->tasksQuery($team, $date, $dayEnd, $openTasksOnly)->get() as $task) {
                $this->pushLine($unitLines, $generalLines, $this->lineFromTask($task));
            }

            foreach ($this->recurringIssuesQuery($team, $date, $dayEnd, $openTasksOnly)->get() as $issue) {
                $this->pushLine($unitLines, $generalLines, $this->lineFromRecurringIssue($issue));
            }

            $unitLines = $unitLines
                ->sortBy(fn (BriefingLineData $line) => [$line->sortKey, $line->locationLabel])
                ->values();
            $generalLines = $generalLines
                ->sortBy(fn (BriefingLineData $line) => $line->locationLabel)
                ->values();
        }

        return new MorningBriefingViewData(
            team: $team,
            teams: $teams,
            date: $date,
            unitLines: $unitLines,
            generalLines: $generalLines,
            lineCount: $unitLines->count() + $generalLines->count(),
            openTasksOnly: $openTasksOnly,
        );
    }

    /**
     * @return Collection<int, InternalTeam>
     */
    private function teamsForTenant(Tenant $tenant): Collection
    {
        return InternalTeam::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, InternalTeam>  $teams
     */
    private function resolveTeam(Collection $teams, ?int $teamId): ?InternalTeam
    {
        if ($teams->isEmpty()) {
            return null;
        }

        if ($teamId !== null) {
            $team = $teams->firstWhere('id', $teamId);

            return $team instanceof InternalTeam ? $team : null;
        }

        if ($teams->count() === 1) {
            return $teams->first();
        }

        return null;
    }

    /**
     * @param  Collection<int, BriefingLineData>  $unitLines
     * @param  Collection<int, BriefingLineData>  $generalLines
     */
    private function pushLine(Collection $unitLines, Collection $generalLines, BriefingLineData $line): void
    {
        if ($line->sortKey === PHP_INT_MAX) {
            $generalLines->push($line);
        } else {
            $unitLines->push($line);
        }
    }

    /**
     * @return Builder<Task>
     */
    private function tasksQuery(InternalTeam $team, Carbon $dayStart, Carbon $dayEnd, bool $openTasksOnly): Builder
    {
        $query = Task::query()
            ->forApprovedIssue()
            ->with(['issue.location', 'issue.unit', 'issue.translations', 'team'])
            ->where('tenant_id', $team->tenant_id)
            ->where('internal_team_id', $team->id)
            ->whereIn('status', TaskStatus::openValues());

        if (! $openTasksOnly) {
            $query->where(function (Builder $dateScoped) use ($dayStart, $dayEnd) {
                $dateScoped
                    ->where(function (Builder $dueOnDay) use ($dayStart, $dayEnd) {
                        $dueOnDay
                            ->whereDate('scheduled_for', $dayStart)
                            ->orWhereBetween('due_at', [$dayStart, $dayEnd]);
                    })
                    ->orWhere(function (Builder $openUndated) {
                        $openUndated
                            ->whereNull('scheduled_for')
                            ->whereNull('due_at')
                            ->where('is_recurring_cycle', false);
                    });
            });
        }

        return $query->orderBy('id');
    }

    /**
     * @return Builder<Issue>
     */
    private function recurringIssuesQuery(InternalTeam $team, Carbon $dayStart, Carbon $dayEnd, bool $openTasksOnly): Builder
    {
        $query = Issue::query()
            ->with(['unit', 'location', 'translations'])
            ->where('tenant_id', $team->tenant_id)
            ->where('is_recurring', true)
            ->where('recurrence_active', true)
            ->whereNull('recurrence_paused_at')
            ->whereNotNull('recurrence_next_due_at')
            ->where(fn (Builder $outer) => $this->scopeIssuesForTeam($outer, (int) $team->id));

        if ($openTasksOnly) {
            $query->where('recurrence_next_due_at', '<=', now()->endOfDay());
        } else {
            $query->whereBetween('recurrence_next_due_at', [$dayStart, $dayEnd]);
        }

        return $query
            ->whereDoesntHave('tasks', function (Builder $taskQuery) use ($dayStart, $dayEnd, $openTasksOnly) {
                $taskQuery
                    ->whereColumn('recurrence_issue_id', 'issues.id')
                    ->whereIn('status', TaskStatus::openValues());

                if (! $openTasksOnly) {
                    $taskQuery->whereBetween('due_at', [$dayStart, $dayEnd]);
                }
            })
            ->orderBy('id');
    }

    private function scopeIssuesForTeam(Builder $query, int $teamId): void
    {
        $query->where(function (Builder $outer) use ($teamId) {
            $outer->whereHas('tasks', fn (Builder $taskQuery) => $taskQuery->where('internal_team_id', $teamId))
                ->orWhereHas('unit', fn (Builder $unitQuery) => $unitQuery->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->whereHas('teams', fn (Builder $teamQuery) => $teamQuery->where('internal_teams.id', $teamId))));
        });
    }

    private function lineFromTask(Task $task): BriefingLineData
    {
        $unit = $task->issue?->unit;
        $locationName = trim((string) ($task->issue?->location?->name ?? ''));

        return new BriefingLineData(
            locationLabel: $this->locationLabelForUnit($unit),
            summary: $this->summaryForTask($task),
            sortKey: $unit instanceof Unit ? $this->unitSortKey($unit->name) : PHP_INT_MAX,
            locationHint: $locationName !== '' ? $locationName : null,
        );
    }

    private function lineFromRecurringIssue(Issue $issue): BriefingLineData
    {
        return new BriefingLineData(
            locationLabel: $this->locationLabelForUnit($issue->unit),
            summary: $this->summaryForRecurringIssue($issue),
            sortKey: $issue->unit instanceof Unit ? $this->unitSortKey($issue->unit->name) : PHP_INT_MAX,
            locationHint: trim((string) ($issue->location?->name ?? '')) ?: null,
        );
    }

    private function locationLabelForUnit(?Unit $unit): string
    {
        if (! $unit instanceof Unit) {
            return __('briefing.general_area_fallback');
        }

        $name = trim((string) $unit->name);

        return $name !== '' ? $name : __('briefing.unit_fallback');
    }

    private function unitSortKey(string $unitName): int
    {
        if (preg_match('/(\d+)/', $unitName, $matches)) {
            return (int) $matches[1];
        }

        return PHP_INT_MAX;
    }

    private function summaryForTask(Task $task): string
    {
        $summary = $this->plainSummaryForTask($task);

        if ($task->is_recurring_cycle || ($task->issue?->is_recurring ?? false)) {
            return $this->withRecurringBadge($summary);
        }

        return $summary;
    }

    private function summaryForRecurringIssue(Issue $issue): string
    {
        $description = trim($issue->localizedDescription());
        $summary = $description !== ''
            ? Str::limit($description, 120)
            : __('briefing.no_description');

        return $this->withRecurringBadge($summary);
    }

    private function withRecurringBadge(string $summary): string
    {
        return $summary.' '.__('briefing.recurring_badge');
    }

    private function plainSummaryForTask(Task $task): string
    {
        $description = trim((string) ($task->description ?? ''));
        if ($description !== '') {
            return Str::limit($description, 120);
        }

        $issueDescription = trim($task->issue?->localizedDescription() ?? '');
        if ($issueDescription !== '') {
            return Str::limit($issueDescription, 120);
        }

        return __('briefing.no_description');
    }
}
