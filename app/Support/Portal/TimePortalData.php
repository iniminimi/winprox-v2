<?php

namespace App\Support\Portal;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Task;
use App\Models\Worker;
use Illuminate\Support\Collection;

/**
 * Lees-queries voor het Time-portaal (inklokken + read-only takenoverzicht).
 */
final class TimePortalData
{
    /**
     * @return Collection<int, Task>
     */
    public static function openTasksForWorker(Worker $worker): Collection
    {
        return self::openTasksForTeam($worker->team);
    }

    /**
     * @return Collection<int, Task>
     */
    public static function openTasksForTeam(InternalTeam $team): Collection
    {
        return Task::where('internal_team_id', $team->id)
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($q) => $q->whereNotNull('approved_at'))
            ->with(['issue', 'issue.location', 'issue.unit.translations', 'issue.translations', 'translations', 'issue.photos', 'issue.updates'])
            ->orderByRaw('CASE priority WHEN "prio_1" THEN 1 WHEN "prio_2" THEN 2 WHEN "prio_3" THEN 3 WHEN "prio_4" THEN 4 ELSE 5 END')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public static function clockPointInactiveReasonKey(ClockPoint $clockPoint): ?string
    {
        return PortalAccess::clockPointInactiveReasonKey($clockPoint);
    }

    /**
     * Open registratie op Clock Point alleen wanneer er precies één actief leeg team is
     * (anders moet de beheerder workers via Team aanmaken).
     */
    public static function openRegistrationTeam(int $tenantId): ?InternalTeam
    {
        $emptyTeams = InternalTeam::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (InternalTeam $team) => self::allowsOpenRegistration($team))
            ->values();

        return $emptyTeams->count() === 1 ? $emptyTeams->first() : null;
    }

    /** Open registratie is toegestaan zolang het team geen actieve workers heeft. */
    public static function allowsOpenRegistration(InternalTeam $team): bool
    {
        return $team->workers()->where('workers.is_active', true)->count() === 0;
    }
}
