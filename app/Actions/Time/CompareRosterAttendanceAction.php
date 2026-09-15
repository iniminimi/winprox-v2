<?php

namespace App\Actions\Time;

use App\Enums\PlannedShiftStatus;
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
    public function handle(int $tenantId, Collection $shifts, array $workerIds, array $dates): array
    {
        if ($workerIds === [] || $dates === []) {
            return [];
        }

        $today = now()->toDateString();
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
                if ($date >= $today) {
                    continue;
                }

                $key = $workerId.':'.$date;
                $planned = $plannedByKey[$key] ?? null;
                $dayPunches = $punches->get($key, collect());
                $hasPunch = $dayPunches->isNotEmpty();

                if ($planned instanceof PlannedShift && ! $planned->status->isPublished()) {
                    $planned = null;
                }

                $result[$key] = $this->status($planned, $hasPunch, $dayPunches)->value;
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
    private function status(?PlannedShift $planned, bool $hasPunch, Collection $dayPunches): RosterAttendanceStatus
    {
        if ($planned === null) {
            return $hasPunch ? RosterAttendanceStatus::Unplanned : RosterAttendanceStatus::None;
        }

        if ($planned->kind->isWork()) {
            if (! $hasPunch) {
                return RosterAttendanceStatus::Missing;
            }

            return $this->workMatches($planned, $dayPunches)
                ? RosterAttendanceStatus::Ok
                : RosterAttendanceStatus::Deviation;
        }

        return $hasPunch ? RosterAttendanceStatus::Unplanned : RosterAttendanceStatus::Ok;
    }

    /**
     * @param  Collection<int, WorkShift>  $dayPunches
     */
    private function workMatches(PlannedShift $planned, Collection $dayPunches): bool
    {
        if ($planned->start_time === null || $planned->end_time === null) {
            return false;
        }

        $start = ShiftType::timeToMinutes($planned->start_time);
        $end = ShiftType::timeToMinutes($planned->end_time);

        foreach ($dayPunches as $punch) {
            $in = ($punch->clock_in_at->hour * 60) + $punch->clock_in_at->minute;
            if ($in > $start) {
                return false;
            }
            if ($in < $start) {
                return false;
            }
            if ($punch->clock_out_at === null) {
                return false;
            }
            $out = ($punch->clock_out_at->hour * 60) + $punch->clock_out_at->minute;
            if ($out < $end) {
                return false;
            }
        }

        return true;
    }
}
