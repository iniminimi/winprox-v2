<?php

namespace App\Actions\Time;

use App\Enums\RosterAttendanceStatus;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CompareRosterAttendanceAction
{
    /**
     * @param  Collection<int, PlannedShift>  $shifts
     * @param  list<int>  $workerIds
     * @param  list<string>  $dates
     * @return array<string, string>
     */
    public function handle(int $tenantId, Collection $shifts, array $workerIds, array $dates, ?Carbon $now = null): array
    {
        if ($workerIds === [] || $dates === []) {
            return [];
        }

        $now = ($now ?? now())->copy();
        $today = $now->toDateString();
        $from = Carbon::parse($dates[0])->startOfDay();
        $to = Carbon::parse($dates[array_key_last($dates)])->endOfDay();

        $punches = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $workerIds)
            ->where('clock_in_at', '>=', $from)
            ->where('clock_in_at', '<=', $to)
            ->get()
            ->groupBy(fn (WorkShift $shift) => $shift->worker_id.':'.$shift->clock_in_at->toDateString());

        $plannedByKey = [];
        foreach ($shifts as $shift) {
            $key = $shift->worker_id.':'.$shift->work_date->toDateString();
            $plannedByKey[$key] = $shift;
        }

        $result = [];
        foreach ($workerIds as $workerId) {
            foreach ($dates as $date) {
                if ($date > $today) {
                    continue;
                }

                $key = $workerId.':'.$date;
                $planned = $plannedByKey[$key] ?? null;
                $dayPunches = $punches->get($key, collect());
                $hasPunch = $dayPunches->isNotEmpty();

                if ($planned instanceof PlannedShift && ! $planned->status->isPublished()) {
                    $planned = null;
                }

                $result[$key] = $this->status($planned, $hasPunch, $dayPunches, $date, $now)->value;
            }
        }

        return array_filter(
            $result,
            fn (string $status) => $status !== RosterAttendanceStatus::None->value,
        );
    }

    /**
     * @param  Collection<int, WorkShift>  $dayPunches
     */
    private function status(
        ?PlannedShift $planned,
        bool $hasPunch,
        Collection $dayPunches,
        string $date,
        Carbon $now,
    ): RosterAttendanceStatus {
        if ($planned === null) {
            return $hasPunch ? RosterAttendanceStatus::Unplanned : RosterAttendanceStatus::None;
        }

        if ($planned->kind->isWork()) {
            if ($hasPunch) {
                return $this->workPunchStatus($planned, $dayPunches, $date, $now);
            }

            return $this->plannedEndPassed($planned, $date, $now)
                ? RosterAttendanceStatus::Missing
                : RosterAttendanceStatus::None;
        }

        return $hasPunch ? RosterAttendanceStatus::Unplanned : RosterAttendanceStatus::Ok;
    }

    /**
     * @param  Collection<int, WorkShift>  $dayPunches
     */
    private function workPunchStatus(
        PlannedShift $planned,
        Collection $dayPunches,
        string $date,
        Carbon $now,
    ): RosterAttendanceStatus {
        if ($planned->start_time === null || $planned->end_time === null) {
            return RosterAttendanceStatus::Deviation;
        }

        $tolerance = $this->toleranceMinutes();
        $start = ShiftType::timeToMinutes($planned->start_time);
        $end = ShiftType::timeToMinutes($planned->end_time);
        $pastClockOutWindow = $this->plannedClockOutWindowPassed($planned, $date, $now, $tolerance);

        foreach ($dayPunches as $punch) {
            $in = $this->punchMinutes($punch->clock_in_at);
            if (! $this->withinAttendanceWindow($in, $start, $tolerance)) {
                return RosterAttendanceStatus::Deviation;
            }

            if ($punch->clock_out_at === null) {
                return $pastClockOutWindow
                    ? RosterAttendanceStatus::Deviation
                    : RosterAttendanceStatus::Ok;
            }

            $out = $this->punchMinutes($punch->clock_out_at);
            if (! $this->withinAttendanceWindow($out, $end, $tolerance)) {
                return RosterAttendanceStatus::Deviation;
            }
        }

        return RosterAttendanceStatus::Ok;
    }

    private function toleranceMinutes(): int
    {
        return max(0, (int) config('time.roster_attendance_tolerance_minutes', 15));
    }

    private function punchMinutes(Carbon $at): int
    {
        return ($at->hour * 60) + $at->minute;
    }

    /**
     * Tot N minuten te vroeg telt mee; N minuten te laat is een afwijking.
     * Tolerance 0 = exact op de geplande minuut.
     */
    private function withinAttendanceWindow(int $actual, int $planned, int $tolerance): bool
    {
        if ($tolerance <= 0) {
            return $actual === $planned;
        }

        return $actual >= ($planned - $tolerance) && $actual < ($planned + $tolerance);
    }

    private function plannedClockOutWindowPassed(
        PlannedShift $planned,
        string $date,
        Carbon $now,
        int $tolerance,
    ): bool {
        if ($date < $now->toDateString()) {
            return true;
        }

        if ($date > $now->toDateString()) {
            return false;
        }

        if ($planned->end_time === null) {
            return $now->greaterThanOrEqualTo(Carbon::parse($date)->endOfDay()->addMinutes($tolerance));
        }

        $windowEnd = Carbon::parse($date)->startOfDay()
            ->addMinutes(ShiftType::timeToMinutes($planned->end_time) + $tolerance);

        return $now->greaterThanOrEqualTo($windowEnd);
    }

    private function plannedEndPassed(PlannedShift $planned, string $date, Carbon $now): bool
    {
        if ($date < $now->toDateString()) {
            return true;
        }

        if ($date > $now->toDateString()) {
            return false;
        }

        if ($planned->end_time === null) {
            return $now->greaterThanOrEqualTo(Carbon::parse($date)->endOfDay());
        }

        $endMinutes = ShiftType::timeToMinutes($planned->end_time);
        $plannedEnd = Carbon::parse($date)->startOfDay()->addMinutes($endMinutes);

        return $now->greaterThanOrEqualTo($plannedEnd);
    }
}
