<?php

namespace App\Actions\Time;

use App\Models\PlannedShift;
use App\Models\RosterRestorePoint;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * De laatste herstelpunten van één tenant, nieuwste eerst.
 * Beoordeelt niets: de keuze en de verschillen komen uit de herstelactie.
 */
class ListRosterRestorePointsAction
{
    public const RECENT_LIMIT = 40;

    /**
     * @return array{points: list<array<string, mixed>>, capped: bool}
     */
    public function handle(Tenant $tenant, User $actor): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if (! SuperuserTenantAccess::canAccessTenant($actor, (int) $tenant->id)
            || ! Gate::forUser($actor)->allows('update', PlannedShift::class)
        ) {
            throw new AuthorizationException;
        }

        $rows = RosterRestorePoint::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT + 1)
            ->get();

        $capped = $rows->count() > self::RECENT_LIMIT;
        $rows = $rows->take(self::RECENT_LIMIT);

        $workerIds = [];
        foreach ($rows as $point) {
            foreach (is_array($point->scope) ? $point->scope : [] as $item) {
                $workerIds[(int) $item['worker_id']] = (int) $item['worker_id'];
            }
        }

        $names = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', array_values($workerIds) ?: [0])
            ->get()
            ->mapWithKeys(fn (Worker $worker): array => [(int) $worker->id => $worker->displayName()])
            ->all();

        $points = [];
        foreach ($rows as $point) {
            $dates = [];
            $workers = [];
            foreach (is_array($point->scope) ? $point->scope : [] as $item) {
                $workerId = (int) $item['worker_id'];
                $dates[(string) $item['date']] = (string) $item['date'];
                if (isset($names[$workerId]) && $names[$workerId] !== '') {
                    $workers[$workerId] = ['id' => $workerId, 'name' => $names[$workerId]];
                }
            }
            $dateList = array_values($dates);
            sort($dateList);

            $points[] = [
                'id' => (int) $point->id,
                'created_at' => $point->created_at?->toIso8601String(),
                'source' => $point->source->value,
                'workers' => array_values($workers),
                'dates' => $dateList,
            ];
        }

        return ['points' => $points, 'capped' => $capped];
    }
}
