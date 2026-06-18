<?php

namespace App\Support\Portal;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Lees-queries voor het team-QR portaal (read-only takenoverzicht; afhandelen
 * kan alleen via de unit-QR ter plaatse). Houdt de Livewire-component dun.
 */
final class TeamPortalData
{
    /**
     * Open taken (Nieuw/In uitvoering) van dit team over alle meldingen.
     *
     * @return Collection<int, Task>
     */
    public static function openTasksForTeam(InternalTeam $team): Collection
    {
        return Task::where('internal_team_id', $team->id)
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($q) => $q->whereNotNull('approved_at'))
            ->with(['issue', 'issue.location', 'issue.unit', 'issue.translations', 'issue.photos', 'issue.updates'])
            ->orderByRaw('CASE priority WHEN "prio_1" THEN 1 WHEN "prio_2" THEN 2 WHEN "prio_3" THEN 3 WHEN "prio_4" THEN 4 ELSE 5 END')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** Open registratie is toegestaan zolang het team geen actieve workers heeft. */
    public static function allowsOpenRegistration(InternalTeam $team): bool
    {
        return $team->workers()->where('workers.is_active', true)->count() === 0;
    }
}
