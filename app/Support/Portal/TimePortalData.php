<?php

namespace App\Support\Portal;

use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Enums\TaskStatus;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkVisit;
use Illuminate\Support\Collection;

/**
 * Lees-queries voor het Time-portaal (inklokken + teamtaken).
 */
final class TimePortalData
{
    /** @var list<string> Former default Clock Point names (login, then clock-in wording). */
    public const FORMER_GENERIC_NAMES = [
        'Inloggen',
        'Sign in',
        'Anmeldung',
        'Connexion',
        'Inicio de sesión',
        'Accesso',
        'Inklokken',
        'Clock in',
        'Einstempeln',
        'Pointage',
        'Fichar',
        'Timbratura',
    ];

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
            ->with(['issue', 'issue.location', 'issue.unit.location', 'issue.unit.translations', 'issue.esgIndicator.translations', 'issue.roundStops.unit.location', 'issue.translations', 'translations', 'issue.photos', 'issue.updates'])
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

    public static function tenantRequiresPin(int $tenantId): bool
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant !== null && $tenant->requiresWorkerPin();
    }

    public static function tenantRequestsClockGps(int $tenantId): bool
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant !== null && $tenant->requestsClockGps();
    }

    public static function tenantAllowsGpsWorkVisits(int $tenantId): bool
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant !== null && $tenant->allowsGpsWorkVisits();
    }

    public static function tenantAllowsEvacuationList(int $tenantId): bool
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant !== null && $tenant->allowsEvacuationList();
    }

    public static function isGenericClockPointName(?string $name): bool
    {
        $name = trim((string) $name);
        if ($name === '') {
            return true;
        }

        foreach (config('locales.supported', []) as $locale) {
            if ($name === trans('team.clock_point_qr.default_name', [], $locale)) {
                return true;
            }
        }

        return in_array($name, self::FORMER_GENERIC_NAMES, true);
    }

    /**
     * Status bovenaan Clock Point tijdens een open GPS-werkbezoek.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  list<array{location_id: int, location_name: string}>  $todayGroups
     * @return array{here: string, next: ?string, remaining_here: bool}|null
     */
    public static function onSiteGuidance(?WorkVisit $visit, Collection $tasks, array $todayGroups): ?array
    {
        if ($visit === null || $visit->location_id === null) {
            return null;
        }

        $visit->loadMissing('location');
        $hereId = (int) $visit->location_id;
        $here = self::locationLabel($visit->location);
        if ($here === '') {
            return null;
        }

        $tasks->loadMissing([
            'issue.location',
            'issue.unit.location',
            'issue.roundStops.unit.location',
        ]);

        $remainingHere = false;
        $nextFromRound = null;
        $nextFromTask = null;
        $roundProgress = app(RoundTaskCompletionAction::class);

        foreach ($tasks as $task) {
            $issue = $task->issue;
            if ($issue === null) {
                continue;
            }

            if ($issue->isInspectionRound()) {
                $nextUnitId = $roundProgress->nextOpenStopUnitId($task);
                if ($nextUnitId === null) {
                    continue;
                }

                $stop = $issue->roundStops?->firstWhere('unit_id', $nextUnitId);
                $location = $stop?->unit?->location;
                $locationId = $location !== null ? (int) $location->id : null;
                if ($locationId === $hereId) {
                    $remainingHere = true;
                } elseif ($nextFromRound === null) {
                    $label = self::locationLabel($location);
                    if ($label !== '') {
                        $nextFromRound = $label;
                    }
                }

                continue;
            }

            $location = $issue->location ?? $issue->unit?->location;
            $locationId = $location !== null ? (int) $location->id : null;
            if ($locationId === $hereId) {
                $remainingHere = true;
            } elseif ($nextFromTask === null) {
                $label = self::locationLabel($location);
                if ($label !== '') {
                    $nextFromTask = $label;
                }
            }
        }

        $nextFromToday = null;
        foreach ($todayGroups as $group) {
            if ((int) ($group['location_id'] ?? 0) === $hereId) {
                continue;
            }

            $label = trim((string) ($group['location_name'] ?? ''));
            if ($label !== '') {
                $nextFromToday = $label;
                break;
            }
        }

        return [
            'here' => $here,
            'next' => $nextFromRound ?? $nextFromTask ?? $nextFromToday,
            'remaining_here' => $remainingHere,
        ];
    }

    private static function locationLabel(?Location $location): string
    {
        if ($location === null) {
            return '';
        }

        $name = trim($location->localizedName());
        if ($name === '') {
            $name = trim((string) $location->name);
        }

        return $name;
    }
}
