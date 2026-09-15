<?php

namespace App\Actions\Time;

use App\Actions\Time\NotifyWorkersRosterPublishedAction;
use App\Data\Time\PublishWeekData;
use App\Enums\PlannedShiftStatus;
use App\Events\Time\SchedulePublished;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;

class PublishWeekAction
{
    public function __construct(
        private ResolveRosterPeriodAction $resolvePeriod,
        private NotifyWorkersRosterPublishedAction $notifyPublished,
    ) {}

    public function handle(Tenant $tenant, PublishWeekData $data, ?int $actorUserId): int
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $period = $data->period === 'month' ? 'month' : 'week';
        [, , $dates] = $this->resolvePeriod->handle($data->weekStart, $period);
        $workerIds = array_values(array_unique(array_map('intval', $data->workerIds)));
        $this->assertWorkersBelongToTenant((int) $tenant->id, $workerIds);

        $count = DB::transaction(function () use ($tenant, $workerIds, $dates, $actorUserId) {
            $shifts = PlannedShift::query()
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$dates[0], $dates[array_key_last($dates)]])
                ->lockForUpdate()
                ->get();

            PlannedShift::query()
                ->whereIn('id', $shifts->pluck('id')->all() ?: [0])
                ->update(['status' => PlannedShiftStatus::Published->value]);

            $count = $shifts->count();

            event(new SchedulePublished(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                weekStart: $dates[0],
                workerIds: $workerIds,
                count: $count,
            ));

            return $count;
        });

        $this->notifyPublished->handle($tenant, $workerIds, $dates[0]);

        return $count;
    }

    /**
     * @param  list<int>  $workerIds
     */
    private function assertWorkersBelongToTenant(int $tenantId, array $workerIds): void
    {
        if ($workerIds === []) {
            return;
        }

        $count = Worker::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $workerIds)
            ->count();

        if ($count !== count($workerIds)) {
            throw new RosterValidationException('time.schedule.errors.unknown_worker');
        }
    }
}
