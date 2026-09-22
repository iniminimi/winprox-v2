<?php

namespace App\Actions\Time;

use App\Data\Time\WorkerRosterEntry;
use App\Data\Time\WorkerRosterSnapshot;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\PlannedShift;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use InvalidArgumentException;

class ListPublishedWorkerRosterAction
{
    public function __construct(private ResolveRosterMonthAction $resolveMonth) {}

    public function handle(Worker $worker, int $tenantId, string $cursor, ?string $locale = null): WorkerRosterSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $locale = $locale ?: app()->getLocale();
        [$start, $end] = $this->resolveMonth->handle($cursor);
        $monthStart = $start->toDateString();
        $monthEnd = $end->toDateString();

        $shifts = PlannedShift::query()
            ->with('shiftType')
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('status', PlannedShiftStatus::Published->value)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $entries = [];
        $previousDate = null;
        foreach ($shifts as $shift) {
            $date = $shift->work_date->copy()->startOfDay();
            $weekStart = $previousDate instanceof Carbon
                && $date->isMonday()
                && $previousDate->lt($date);
            $entries[] = new WorkerRosterEntry(
                date: $date->toDateString(),
                dayLabel: $this->dayLabel($shift, $locale),
                duty: $this->duty($shift),
                hours: $this->hours($shift),
                kind: $shift->kind->value,
                weekStart: $weekStart,
                isToday: $date->isToday(),
            );
            $previousDate = $date;
        }

        return new WorkerRosterSnapshot(
            monthStart: $monthStart,
            monthEnd: $monthEnd,
            monthLabel: $start->locale($locale)->translatedFormat('F Y'),
            entries: $entries,
        );
    }

    private function dayLabel(PlannedShift $shift, string $locale): string
    {
        $day = $shift->work_date->copy()->locale($locale);

        return $day->isoFormat('dd').' '.$day->format('d/m');
    }

    private function duty(PlannedShift $shift): string
    {
        if ($shift->kind->isAbsence()) {
            return __('time.schedule.types.kinds.'.$shift->kind->value);
        }

        $type = $shift->shiftType;
        $label = ($type !== null && $type->is_active && $type->kind === ShiftTypeKind::Work)
            ? $type->label
            : '';
        $place = is_string($shift->unit_name) && $shift->unit_name !== ''
            ? $shift->unit_name
            : '';

        if ($label !== '' && $place !== '') {
            return $label.' · '.$place;
        }

        return $label !== '' ? $label : $place;
    }

    private function hours(PlannedShift $shift): string
    {
        if ($shift->kind->isAbsence()) {
            return '';
        }

        $start = $shift->start_time !== null ? substr((string) $shift->start_time, 0, 5) : '';
        $end = $shift->end_time !== null ? substr((string) $shift->end_time, 0, 5) : '';

        return $start !== '' && $end !== '' ? $start.'-'.$end : '';
    }
}
