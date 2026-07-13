<?php

namespace App\Actions\Time;

use App\Data\Time\TimePresenceDashboard;
use App\Data\Time\TimePresenceKpis;
use App\Data\Time\TimePresenceTeamBucket;
use App\Enums\TimePresenceStatusFilter;
use App\Enums\WorkShiftStatus;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Time\TimePresenceAttentionRules;
use Illuminate\Support\Collection;

class BuildTimePresenceDashboardAction
{
    public function handle(
        int $tenantId,
        ?int $teamId = null,
        ?int $clockPointId = null,
        ?int $locationId = null,
        ?string $search = null,
        TimePresenceStatusFilter $statusFilter = TimePresenceStatusFilter::All,
    ): TimePresenceDashboard {
        $needle = mb_strtolower(trim((string) $search));
        $isSearchMode = $needle !== '';

        $clockPointIdsForLocation = $this->clockPointIdsForLocation($tenantId, $locationId);

        $openShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->with(['worker.team', 'openBreak', 'clockInClockPoint', 'breaks'])
            ->when($teamId, fn ($q) => $q->where('internal_team_id', $teamId))
            ->when($clockPointId, fn ($q) => $q->where('clock_in_clock_point_id', $clockPointId))
            ->when($clockPointIdsForLocation !== null, fn ($q) => $q->whereIn('clock_in_clock_point_id', $clockPointIdsForLocation))
            ->when($isSearchMode, function ($q) use ($needle) {
                $q->whereHas('worker', function ($workerQuery) use ($needle) {
                    $workerQuery->whereRaw('LOWER(first_name) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$needle.'%']);
                });
            })
            ->orderBy('clock_in_at')
            ->get();

        $onBreak = $openShifts->filter(fn (WorkShift $shift) => $shift->openBreak !== null)->values();
        $active = $openShifts->filter(fn (WorkShift $shift) => $shift->openBreak === null)->values();
        $attentionItems = TimePresenceAttentionRules::collect($openShifts);

        $clockedInWorkerIds = $openShifts->pluck('worker_id')->all();
        $absentCount = $this->countAbsentWorkers($tenantId, $teamId, $clockedInWorkerIds, $needle, $clockPointId, $locationId);

        $kpis = new TimePresenceKpis(
            clockedIn: $active->count() + $onBreak->count(),
            active: $active->count(),
            onBreak: $onBreak->count(),
            notClockedIn: $absentCount,
            attention: $attentionItems->count(),
        );

        if ($isSearchMode) {
            return new TimePresenceDashboard(
                kpis: $kpis,
                attentionItems: $this->filterAttention($attentionItems, $statusFilter),
                teamBuckets: collect(),
                searchShifts: $this->filterShiftsForStatus($openShifts, $statusFilter),
                searchAbsentWorkers: $this->loadAbsentWorkers(
                    $tenantId,
                    $teamId,
                    $clockedInWorkerIds,
                    $needle,
                    $clockPointId,
                    $locationId,
                    $statusFilter,
                ),
                isSearchMode: true,
            );
        }

        $teams = InternalTeam::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($teamId, fn ($q) => $q->where('id', $teamId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $absentByTeam = $this->absentCountsByTeam(
            $tenantId,
            $teamId,
            $clockedInWorkerIds,
            $needle,
            $clockPointId,
            $locationId,
        );

        $teamBuckets = $teams->map(function (InternalTeam $team) use (
            $active,
            $onBreak,
            $attentionItems,
            $absentByTeam,
            $statusFilter,
            $tenantId,
            $clockedInWorkerIds,
            $needle,
            $clockPointId,
            $locationId,
        ) {
            $teamActive = $active->where('internal_team_id', $team->id)->values();
            $teamBreak = $onBreak->where('internal_team_id', $team->id)->values();
            $teamAttention = $attentionItems->filter(
                fn ($item) => (int) $item->shift->internal_team_id === (int) $team->id
            )->count();
            $teamAbsentCount = (int) ($absentByTeam[$team->id] ?? 0);

            $loadAbsent = $statusFilter === TimePresenceStatusFilter::Absent && $teamAbsentCount > 0;

            return new TimePresenceTeamBucket(
                team: $team,
                activeCount: $teamActive->count(),
                breakCount: $teamBreak->count(),
                absentCount: $teamAbsentCount,
                attentionCount: $teamAttention,
                activeShifts: $teamActive,
                breakShifts: $teamBreak,
                absentWorkers: $loadAbsent
                    ? $this->loadAbsentWorkersForTeam($tenantId, $team->id, $clockedInWorkerIds, $needle)
                    : collect(),
            );
        })->filter(function (TimePresenceTeamBucket $bucket) use ($statusFilter) {
            if ($statusFilter === TimePresenceStatusFilter::Active) {
                return $bucket->activeCount > 0;
            }
            if ($statusFilter === TimePresenceStatusFilter::Break) {
                return $bucket->breakCount > 0;
            }
            if ($statusFilter === TimePresenceStatusFilter::Absent) {
                return $bucket->absentCount > 0;
            }
            if ($statusFilter === TimePresenceStatusFilter::Attention) {
                return $bucket->attentionCount > 0;
            }

            return $bucket->hasActivity();
        })->values();

        return new TimePresenceDashboard(
            kpis: $kpis,
            attentionItems: $this->filterAttention($attentionItems, $statusFilter),
            teamBuckets: $teamBuckets,
            searchShifts: collect(),
            searchAbsentWorkers: collect(),
            isSearchMode: false,
        );
    }

    /** @return list<int>|null */
    private function clockPointIdsForLocation(int $tenantId, ?int $locationId): ?array
    {
        if ($locationId === null) {
            return null;
        }

        return ClockPoint::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $clockedInWorkerIds
     */
    private function countAbsentWorkers(
        int $tenantId,
        ?int $teamId,
        array $clockedInWorkerIds,
        string $needle,
        ?int $clockPointId,
        ?int $locationId,
    ): int {
        if ($clockPointId !== null || $locationId !== null) {
            return 0;
        }

        return $this->absentWorkersQuery($tenantId, $teamId, $clockedInWorkerIds, $needle)->count();
    }

    /**
     * @param  list<int>  $clockedInWorkerIds
     * @return array<int, int>
     */
    private function absentCountsByTeam(
        int $tenantId,
        ?int $teamId,
        array $clockedInWorkerIds,
        string $needle,
        ?int $clockPointId,
        ?int $locationId,
    ): array {
        if ($clockPointId !== null || $locationId !== null) {
            return [];
        }

        return $this->absentWorkersQuery($tenantId, $teamId, $clockedInWorkerIds, $needle)
            ->selectRaw('internal_team_id, COUNT(*) as aggregate')
            ->groupBy('internal_team_id')
            ->pluck('aggregate', 'internal_team_id')
            ->all();
    }

    /**
     * @param  list<int>  $clockedInWorkerIds
     * @return \Illuminate\Database\Eloquent\Builder<Worker>
     */
    private function absentWorkersQuery(int $tenantId, ?int $teamId, array $clockedInWorkerIds, string $needle)
    {
        return Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($teamId, fn ($q) => $q->where('internal_team_id', $teamId))
            ->when($clockedInWorkerIds !== [], fn ($q) => $q->whereNotIn('id', $clockedInWorkerIds))
            ->when($needle !== '', function ($q) use ($needle) {
                $q->where(function ($inner) use ($needle) {
                    $inner->whereRaw('LOWER(first_name) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$needle.'%']);
                });
            });
    }

    /**
     * @param  list<int>  $clockedInWorkerIds
     * @return Collection<int, Worker>
     */
    private function loadAbsentWorkers(
        int $tenantId,
        ?int $teamId,
        array $clockedInWorkerIds,
        string $needle,
        ?int $clockPointId,
        ?int $locationId,
        TimePresenceStatusFilter $statusFilter,
    ): Collection {
        if ($statusFilter !== TimePresenceStatusFilter::Absent && $statusFilter !== TimePresenceStatusFilter::All) {
            return collect();
        }

        if ($clockPointId !== null || $locationId !== null) {
            return collect();
        }

        return $this->absentWorkersQuery($tenantId, $teamId, $clockedInWorkerIds, $needle)
            ->with('team')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(100)
            ->get();
    }

    /**
     * @param  list<int>  $clockedInWorkerIds
     * @return Collection<int, Worker>
     */
    private function loadAbsentWorkersForTeam(
        int $tenantId,
        int $teamId,
        array $clockedInWorkerIds,
        string $needle,
    ): Collection {
        return $this->absentWorkersQuery($tenantId, $teamId, $clockedInWorkerIds, $needle)
            ->with('team')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(50)
            ->get();
    }

    /**
     * @param  Collection<int, WorkShift>  $shifts
     * @return Collection<int, WorkShift>
     */
    private function filterShiftsForStatus(Collection $shifts, TimePresenceStatusFilter $statusFilter): Collection
    {
        return match ($statusFilter) {
            TimePresenceStatusFilter::Active => $shifts->filter(fn (WorkShift $s) => $s->openBreak === null)->values(),
            TimePresenceStatusFilter::Break => $shifts->filter(fn (WorkShift $s) => $s->openBreak !== null)->values(),
            TimePresenceStatusFilter::Attention => collect(),
            TimePresenceStatusFilter::Absent => collect(),
            TimePresenceStatusFilter::All => $shifts->values(),
        };
    }

    /**
     * @param  Collection<int, \App\Data\Time\TimePresenceAttentionItem>  $items
     * @return Collection<int, \App\Data\Time\TimePresenceAttentionItem>
     */
    private function filterAttention(Collection $items, TimePresenceStatusFilter $statusFilter): Collection
    {
        if ($statusFilter === TimePresenceStatusFilter::Attention) {
            return $items->values();
        }

        if ($statusFilter === TimePresenceStatusFilter::All) {
            return $items->values();
        }

        return collect();
    }
}
