<?php

namespace App\Actions\Team;

use App\Models\Worker;
use Illuminate\Support\Facades\DB;

/**
 * Geeft een nieuwe uitvoerder de standaardwerkregeling: contractnorm
 * 2280 minuten als die ontbreekt en de zeven weekdagminuten volgens
 * Worker::defaultWeekPattern(). Doet niets bij een bestaand patroon
 * of voor tenants zonder de Time-module.
 */
class SeedWorkerWeekPatternAction
{
    public function __construct(private SyncWorkerWeekdayMinutesAction $sync) {}

    public function handle(Worker $worker, ?int $actorUserId = null): bool
    {
        $tenant = $worker->tenant;
        if ($tenant === null || ! $tenant->hasTimeModule()) {
            return false;
        }
        if ($worker->weekdayMinutes()->exists()) {
            return false;
        }

        DB::transaction(function () use ($worker, $actorUserId): void {
            if ($worker->weekly_contract_minutes === null) {
                $worker->forceFill([
                    'weekly_contract_minutes' => Worker::DEFAULT_WEEKLY_CONTRACT_MINUTES,
                ])->save();
            }

            $this->sync->handle(
                (int) $worker->tenant_id,
                $worker->fresh(),
                Worker::defaultWeekPattern((int) $worker->weekly_contract_minutes),
                $actorUserId,
            );
        });

        return true;
    }
}
