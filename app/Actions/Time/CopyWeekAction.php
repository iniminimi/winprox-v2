<?php

namespace App\Actions\Time;

use App\Data\Time\CopyWeekData;
use App\Enums\PlannedShiftStatus;
use App\Events\Time\ScheduleCopied;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;

class CopyWeekAction
{
    public function __construct(private ResolveRosterWeekAction $resolveWeek) {}

    /**
     * @return list<PlannedShift>
     */
    public function handle(Tenant $tenant, CopyWeekData $data, ?int $actorUserId): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        [$sourceMonday, , $sourceDates] = $this->resolveWeek->handle($data->sourceWeekStart);
        [$targetMonday, , $targetDates] = $this->resolveWeek->handle($data->targetWeekStart);

        if ($sourceMonday->toDateString() === $targetMonday->toDateString()) {
            throw new RosterValidationException('time.schedule.errors.copy_same_week');
        }

        $workerIds = array_values(array_unique(array_map('intval', $data->workerIds)));
        $this->assertWorkersBelongToTenant((int) $tenant->id, $workerIds);

        return DB::transaction(function () use ($tenant, $workerIds, $sourceDates, $targetDates, $sourceMonday, $targetMonday, $actorUserId) {
            $source = PlannedShift::query()
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$sourceDates[0], $sourceDates[6]])
                ->lockForUpdate()
                ->get();

            $target = PlannedShift::query()
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$targetDates[0], $targetDates[6]])
                ->lockForUpdate()
                ->get();

            if ($target->isNotEmpty()) {
                throw new RosterValidationException('time.schedule.errors.copy_conflict');
            }

            $offsetDays = (int) round(($targetMonday->getTimestamp() - $sourceMonday->getTimestamp()) / 86400);
            $created = [];
            foreach ($source as $shift) {
                $created[] = PlannedShift::create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $shift->worker_id,
                    'work_date' => $shift->work_date->copy()->addDays($offsetDays)->toDateString(),
                    'shift_type_id' => $shift->shift_type_id,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'break_minutes' => $shift->break_minutes,
                    'status' => PlannedShiftStatus::Draft,
                ]);
            }

            event(new ScheduleCopied(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                sourceWeekStart: $sourceMonday->toDateString(),
                targetWeekStart: $targetMonday->toDateString(),
                workerIds: $workerIds,
                count: count($created),
            ));

            return $created;
        });
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
