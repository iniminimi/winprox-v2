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
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ResolveWorkerPortalRosterAlertsAction
{
    public function __construct(
        private CollectAbsenceClockInAttentionAction $absenceClockIns,
    ) {}

    /**
     * @param  list<string>  $dates
     * @return array<string, list<PortalRosterClockAlert>>
     */
    public function handle(Worker $worker, int $tenantId, array $dates, ?Carbon $now = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if (! TimeModuleAccess::rosterEnabledForTenantId($tenantId)) {
            return [];
        }

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
        $absenceByDate = $this->absenceKindsByDate($tenantId, $punches->collapse());
        $alerts = [];

        foreach ($dates as $date) {
            if ($date > $today) {
                continue;
            }

            $dayPunches = $punches->get($date) ?? collect();
            $dayAlerts = [];

            $dayPlans = $planned->get($date) ?? collect();
            $dayPlan = $dayPlans->first();
            if ($dayPlan instanceof PlannedShift) {
                $dayAlerts = $this->alertsForDay($dayPlan, $dayPlans, $dayPunches, $date, $now, $tolerance);
            }

            $absenceKind = $absenceByDate[$date] ?? null;
            if ($absenceKind !== null) {
                $dayAlerts[] = match ($absenceKind) {
                    'sick' => PortalRosterClockAlert::AbsenceSick,
                    'leave' => PortalRosterClockAlert::AbsenceLeave,
                    'recup' => PortalRosterClockAlert::AbsenceRecup,
                    default => PortalRosterClockAlert::AbsenceHoliday,
                };
            }

            if ($dayAlerts !== []) {
                $alerts[$date] = $dayAlerts;
            }
        }

        return $alerts;
    }

    /**
     * Prik op een gepubliceerde afwezigheidsdag (verlof/recup/ziek) of op een
     * feestdag die niet 'geen effect' telt → hoogst gerankte soort per datum.
     *
     * @param  Collection<int, WorkShift>  $punches
     * @return array<string, string>
     */
    private function absenceKindsByDate(int $tenantId, Collection $punches): array
    {
        if ($punches->isEmpty()) {
            return [];
        }

        $rank = ['sick' => 0, 'leave' => 1, 'recup' => 2, 'holiday' => 3];
        $byDate = [];
        foreach ($this->absenceClockIns->handle($tenantId, $punches, PlannedShiftStatus::Published) as $item) {
            $date = $item->shift?->clock_in_at?->toDateString();
            $kind = $item->absenceKind;
            if ($date === null || ! is_string($kind)) {
                continue;
            }
            if (! isset($byDate[$date]) || ($rank[$kind] ?? 9) < ($rank[$byDate[$date]] ?? 9)) {
                $byDate[$date] = $kind;
            }
        }

        return $byDate;
    }

    /**
     * @param  Collection<int, PlannedShift>  $dayPlans
     * @param  Collection<int, WorkShift>  $dayPunches
     * @return list<PortalRosterClockAlert>
     */
    private function alertsForDay(
        PlannedShift $dayPlan,
        Collection $dayPlans,
        Collection $dayPunches,
        string $date,
        Carbon $now,
        int $tolerance,
    ): array {
        $alerts = [];
        $clockIn = $this->alertForDay($dayPlan, $dayPunches->first()?->clock_in_at, $date, $now, $tolerance);
        if ($clockIn instanceof PortalRosterClockAlert) {
            $alerts[] = $clockIn;
        }

        $clockOut = $this->lateOutAlert($dayPlans, $dayPunches, $date, $tolerance);
        if ($clockOut instanceof PortalRosterClockAlert) {
            $alerts[] = $clockOut;
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, PlannedShift>  $dayPlans
     * @param  Collection<int, WorkShift>  $dayPunches
     */
    private function lateOutAlert(Collection $dayPlans, Collection $dayPunches, string $date, int $tolerance): ?PortalRosterClockAlert
    {
        if ($dayPunches->isEmpty()) {
            return null;
        }

        $stillOpen = $dayPunches->contains(
            fn (WorkShift $shift) => $shift->clock_out_at === null || $shift->status->isOpen()
        );
        if ($stillOpen) {
            return null;
        }

        $endPlan = $dayPlans->reverse()->first(
            fn (PlannedShift $plan) => $plan->end_time !== null
        );
        $lastOut = $dayPunches->max('clock_out_at');
        if (! $endPlan instanceof PlannedShift || ! $lastOut instanceof Carbon) {
            return null;
        }

        $endMinutes = ShiftType::timeToMinutes($endPlan->end_time);
        $plannedEnd = Carbon::parse($date)->startOfDay()->addMinutes($endMinutes);
        if ($endPlan->start_time !== null && $endMinutes <= ShiftType::timeToMinutes($endPlan->start_time)) {
            $plannedEnd->addDay();
        }

        return $lastOut->greaterThanOrEqualTo($plannedEnd->addMinutes($tolerance))
            ? PortalRosterClockAlert::LateOut
            : null;
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

        $earlyBefore = $plannedStart->copy()->subMinutes($tolerance);
        if ($firstIn->lessThan($earlyBefore)) {
            return PortalRosterClockAlert::Early;
        }

        $lateFrom = $plannedStart->copy()->addMinutes($tolerance);

        return $firstIn->greaterThanOrEqualTo($lateFrom)
            ? PortalRosterClockAlert::Late
            : null;
    }
}
