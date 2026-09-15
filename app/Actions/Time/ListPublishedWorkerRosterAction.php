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
        foreach ($shifts as $shift) {
            $entries[] = new WorkerRosterEntry(
                date: $shift->work_date->toDateString(),
                line: $this->line($shift, $locale),
                kind: $shift->kind->value,
            );
        }

        return new WorkerRosterSnapshot(
            monthStart: $monthStart,
            monthEnd: $monthEnd,
            monthLabel: $start->locale($locale)->translatedFormat('F Y'),
            entries: $entries,
        );
    }

    private function line(PlannedShift $shift, string $locale): string
    {
        $day = $shift->work_date->copy()->locale($locale);
        $prefix = $day->isoFormat('dd').' '.$day->format('d/m');

        if ($shift->kind->isAbsence()) {
            return $prefix.' : '.__('time.schedule.types.kinds.'.$shift->kind->value);
        }

        $start = $shift->start_time !== null ? substr((string) $shift->start_time, 0, 5) : '';
        $end = $shift->end_time !== null ? substr((string) $shift->end_time, 0, 5) : '';
        $window = $start !== '' && $end !== '' ? $start.'-'.$end : '';

        $type = $shift->shiftType;
        if ($type !== null && $type->is_active && $type->kind === ShiftTypeKind::Work) {
            $duty = $window !== '' ? $type->label.' - '.$window : $type->label;
        } else {
            $duty = $window;
        }

        $place = is_string($shift->unit_name) && $shift->unit_name !== ''
            ? $shift->unit_name
            : '';

        if ($place !== '' && $duty !== '') {
            $duty .= ' · '.$place;
        } elseif ($place !== '') {
            $duty = $place;
        }

        return $prefix.' : '.$duty;
    }
}
