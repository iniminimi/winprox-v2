<?php

namespace App\Actions\Time;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

/**
 * GPS-werkbezoek is the Clock Point stand-in for a unit sticker:
 * start/complete only while an open visit matches the task's customer location.
 */
class AssertClockPointTaskVisitAction
{
    public function __construct(
        private FindOpenWorkShiftForWorkerAction $findShift,
    ) {}

    public function handle(Worker $worker, Task $task): void
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        $tenant = Tenant::query()->find((int) $worker->tenant_id);
        if ($tenant === null || ! $tenant->allowsGpsWorkVisits()) {
            throw new InvalidArgumentException('clock_point_task_read_only');
        }

        if ((int) $task->tenant_id !== (int) $worker->tenant_id) {
            throw new InvalidArgumentException('clock_point_visit_location_mismatch');
        }

        if ((int) $task->internal_team_id !== (int) $worker->internal_team_id) {
            throw new InvalidArgumentException('clock_point_visit_location_mismatch');
        }

        $task->loadMissing(['issue.unit']);
        if ($task->issue?->isInspectionRound()) {
            throw new InvalidArgumentException('clock_point_task_read_only');
        }

        $shift = $this->findShift->handle($worker);
        $visit = $shift?->openVisit;
        if ($visit === null) {
            throw new InvalidArgumentException('clock_point_visit_required');
        }

        $locationId = self::taskLocationId($task);
        if ($locationId === null || (int) $visit->location_id !== $locationId) {
            throw new InvalidArgumentException('clock_point_visit_location_mismatch');
        }
    }

    public static function taskLocationId(Task $task): ?int
    {
        $issue = $task->issue;
        if ($issue === null) {
            return null;
        }

        if ($issue->location_id !== null) {
            return (int) $issue->location_id;
        }

        $unitLocationId = $issue->unit?->location_id;

        return $unitLocationId !== null ? (int) $unitLocationId : null;
    }
}
