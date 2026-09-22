<?php

namespace App\Actions\Time;

use App\Enums\PlannedShiftStatus;
use App\Enums\PortalRosterClockAlert;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\WorkShift;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use InvalidArgumentException;

class ResolveWorkerPortalRosterAlertsAction
{
    /**
     * @param  list<string>  $dates
     * @return array<string, PortalRosterClockAlert>
     */
    public function handle(Worker $worker, int $tenantId, array $dates, ?Carbon $now = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $dates = array_values(array_unique(array_filter($dates)));
        if ($dates === []) {
            return [];
        }

        $now = ($now ?? now())->copy();
        $today = $now->toDateString();
        sort($dates);

        $from = Carbon::parse($dates[0])->startOfDay();
        $to = Carbon::parse($dates[array_key_last($dates)])->endOfDay();

        $planned = PlannedShift::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('status', PlannedShiftStatus::Published)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->filter(fn (PlannedShift $shift) => $shift->kind->isWork())
            ->groupBy(fn (PlannedShift $shift) => $shift->work_date->toDateString());

        $punches = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('clock_in_at', '>=', $from)
            ->where('clock_in_at', '<=', $to)
            ->orderBy('clock_in_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WorkShift $shift) => $shift->clock_in_at->toDateString());

        $tolerance = max(0, (int) config('time.roster_attendance_tolerance_minutes', 15));
        $alerts = [];

        foreach ($dates as $date) {
            if ($date > $today) {
                continue;
            }

            $dayPlan = $planned->get($date)?->first();
            if (! $dayPlan instanceof PlannedShift) {
                continue;
            }

            $firstIn = $punches->get($date)?->first()?->clock_in_at;
            $alert = $this->alertForDay($dayPlan, $firstIn, $date, $now, $tolerance);
            if ($alert instanceof PortalRosterClockAlert) {
                $alerts[$date] = $alert;
            }
        }

        return $alerts;
    }

    private function alertForDay(
        PlannedShift $planned,
        ?Carbon $firstIn,
        string $date,
        Carbon $now,
        int $tolerance,
    ): ?PortalRosterClockAlert {
        if ($planned->start_time === null) {
            if ($firstIn === null && $date < $now->toDateString()) {
                return PortalRosterClockAlert::Absent;
            }

            return null;
        }

        $plannedStart = Carbon::parse($date)->startOfDay()
            ->addMinutes(ShiftType::timeToMinutes($planned->start_time));

        if ($firstIn === null) {
            return $now->greaterThanOrEqualTo($plannedStart)
                ? PortalRosterClockAlert::Absent
                : null;
        }

        $lateFrom = $plannedStart->copy()->addMinutes($tolerance);

        return $firstIn->greaterThanOrEqualTo($lateFrom)
            ? PortalRosterClockAlert::Late
            : null;
    }
}
