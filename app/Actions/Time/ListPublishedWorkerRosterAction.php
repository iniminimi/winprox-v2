<?php

namespace App\Actions\Time;

use App\Data\Time\WorkerRosterEntry;
use App\Data\Time\WorkerRosterSnapshot;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\PlannedShift;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class ListPublishedWorkerRosterAction
{
    public function __construct(private ResolveRosterWeekAction $resolveWeek) {}

    public function handle(Worker $worker, int $tenantId, string $weekStart): WorkerRosterSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        [$monday, , $dates] = $this->resolveWeek->handle($weekStart);
        $weekStartDate = $dates[0];
        $weekEndDate = $dates[6];

        $shifts = PlannedShift::query()
            ->with('shiftType')
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('status', PlannedShiftStatus::Published->value)
            ->whereBetween('work_date', [$weekStartDate, $weekEndDate])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $entries = [];
        foreach ($shifts as $shift) {
            $entries[] = new WorkerRosterEntry(
                date: $shift->work_date->toDateString(),
                label: $this->label($shift),
                kind: $shift->kind->value,
            );
        }

        return new WorkerRosterSnapshot(
            weekStart: $monday->toDateString(),
            weekEnd: $weekEndDate,
            dates: $dates,
            entries: $entries,
        );
    }

    private function label(PlannedShift $shift): string
    {
        if ($shift->kind->isAbsence()) {
            return __('time.schedule.types.kinds.'.$shift->kind->value);
        }

        $start = $shift->start_time !== null ? substr($shift->start_time, 0, 5) : '';
        $end = $shift->end_time !== null ? substr($shift->end_time, 0, 5) : '';
        $window = $start !== '' && $end !== '' ? $start.'–'.$end : '';

        $type = $shift->shiftType;
        if ($type !== null && $type->is_active && $type->kind === ShiftTypeKind::Work) {
            $base = trim($type->label.' '.$window);
        } else {
            $base = $window;
        }

        $place = is_string($shift->unit_name) && $shift->unit_name !== ''
            ? $shift->unit_name
            : '';

        return $place !== '' ? trim($base.' · '.$place) : $base;
    }
}
