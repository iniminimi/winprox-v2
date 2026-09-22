<?php

namespace App\Actions\Time;

use App\Data\Time\WorkerHoursDay;
use App\Data\Time\WorkerHoursSnapshot;
use App\Models\WorkShift;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use App\Support\Time\WorkDurationFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ListWorkerHoursAction
{
    /** Eigen diensten van één uitvoerder in een periode, nieuwste eerst, samengevoegd per dag. */
    public function handle(Worker $worker, int $tenantId, Carbon $from, Carbon $to): WorkerHoursSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $shifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('clock_in_at', '>=', $from)
            ->where('clock_in_at', '<=', $to)
            ->orderByDesc('clock_in_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $totalNetMinutes = $shifts->sum(fn (WorkShift $shift) => $shift->netWorkMinutes());
        $days = $shifts
            ->groupBy(fn (WorkShift $shift) => $shift->clock_in_at->toDateString())
            ->map(fn (Collection $dayShifts, string $dateKey) => $this->dayFromShifts($dateKey, $dayShifts))
            ->sortByDesc(fn (WorkerHoursDay $day) => $day->dateKey)
            ->values();

        return new WorkerHoursSnapshot(
            shifts: $shifts,
            days: $days,
            totalNetMinutes: $totalNetMinutes,
            from: $from,
            to: $to,
        );
    }

    /**
     * @param  Collection<int, WorkShift>  $dayShifts
     */
    private function dayFromShifts(string $dateKey, Collection $dayShifts): WorkerHoursDay
    {
        $sorted = $dayShifts->sortBy([
            fn (WorkShift $shift) => $shift->clock_in_at->timestamp,
            fn (WorkShift $shift) => $shift->id,
        ])->values();

        $firstIn = $sorted->first()->clock_in_at;
        $isOpen = $sorted->contains(
            fn (WorkShift $shift) => $shift->status->isOpen() || $shift->clock_out_at === null
        );
        $lastOut = $isOpen ? null : $sorted->max('clock_out_at');
        $worked = $sorted->sum(fn (WorkShift $shift) => $shift->netWorkMinutes());
        $break = $sorted->sum(fn (WorkShift $shift) => (int) $shift->total_break_minutes);
        $hour = __('time.duration.hour_short');

        return new WorkerHoursDay(
            dateKey: $dateKey,
            dateLabel: $firstIn->format('d-m-Y'),
            isOpen: $isOpen,
            timesLine: __('time.portal.hours.day_line', [
                'in' => $firstIn->format('H:i').$hour,
                'out' => $lastOut
                    ? $lastOut->format('H:i').$hour
                    : __('time.portal.hours.out_open'),
                'worked' => $this->formatPortalDuration($worked),
            ]),
            breakLine: __('time.portal.hours.break_line', [
                'break' => $break > 0
                    ? $this->formatPortalDuration($break)
                    : __('time.portal.hours.break_none'),
            ]),
        );
    }

    private function formatPortalDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes < 60) {
            return $minutes.' '.__('time.duration.minute_short');
        }

        return WorkDurationFormatter::format($minutes);
    }
}
