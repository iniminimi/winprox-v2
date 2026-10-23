<?php

namespace App\Actions\Time;

use App\Data\Time\TeamRosterDay;
use App\Data\Time\TeamRosterEntry;
use App\Data\Time\TeamRosterWeekSnapshot;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\PlannedShift;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Teamrooster voor het gsm-portaal: gepubliceerde diensten van collega's
 * binnen de locaties van de kijker. Scope = aan die locatie(s) toegewezen
 * uitvoerders (alle diensten) + iedereen met een dienst op die locatie
 * (invallers). Afwezigheden worden neutraal getoond.
 */
class ListPublishedLocationRosterAction
{
    /**
     * @param  list<int>  $locationIds
     */
    public function handle(Worker $viewer, int $tenantId, array $locationIds, string $cursor, ?string $locale = null): TeamRosterWeekSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $viewer->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $locationIds = array_values(array_unique(array_map('intval', $locationIds)));
        $locale = $locale ?: app()->getLocale();
        $start = Carbon::parse($cursor)->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        $assignedIds = $locationIds === [] ? [] : Worker::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('locations', fn ($q) => $q->whereIn('locations.id', $locationIds))
            ->pluck('id');

        $shifts = $locationIds === [] && $assignedIds->isEmpty()
            ? collect()
            : PlannedShift::query()
                ->with('shiftType', 'worker')
                ->where('tenant_id', $tenantId)
                ->where('status', PlannedShiftStatus::Published->value)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->where(function ($q) use ($assignedIds, $locationIds): void {
                    if ($assignedIds->isNotEmpty()) {
                        $q->orWhereIn('worker_id', $assignedIds);
                    }
                    if ($locationIds !== []) {
                        $q->orWhereIn('location_id', $locationIds);
                    }
                })
                ->orderBy('work_date')
                ->orderBy('start_time')
                ->orderBy('id')
                ->get();

        $absentLabel = __('time.portal.team_schedule.absent');
        $days = [];
        foreach ($shifts->groupBy(fn (PlannedShift $s) => $s->work_date->toDateString()) as $date => $dayShifts) {
            $day = Carbon::parse($date);
            $entries = $dayShifts
                ->sortBy(fn (PlannedShift $s) => [
                    $s->kind->isAbsence() ? '1' : '0',
                    $s->start_time ?? '99:99',
                    $s->worker?->displayName() ?? '',
                ])
                ->map(fn (PlannedShift $s) => new TeamRosterEntry(
                    workerName: $s->worker?->displayName() ?? '—',
                    isViewer: (int) $s->worker_id === (int) $viewer->id,
                    duty: $s->kind->isAbsence() ? $absentLabel : $this->duty($s),
                    hours: $this->hours($s),
                    kind: $s->kind->value,
                ))
                ->values()
                ->all();

            $days[] = new TeamRosterDay(
                date: $date,
                dayLabel: $day->copy()->locale($locale)->isoFormat('dd').' '.$day->format('d/m'),
                isToday: $day->isToday(),
                entries: $entries,
            );
        }

        return new TeamRosterWeekSnapshot(
            weekStart: $start->toDateString(),
            weekEnd: $end->toDateString(),
            weekLabel: __('time.portal.team_schedule.week_label', [
                'week' => $start->isoWeek(),
                'range' => $start->format('d/m').' – '.$end->format('d/m'),
            ]),
            days: $days,
        );
    }

    private function duty(PlannedShift $shift): string
    {
        $type = $shift->shiftType;
        $label = ($type !== null && $type->is_active && $type->kind === ShiftTypeKind::Work)
            ? $type->label
            : '';
        $place = is_string($shift->unit_name) && $shift->unit_name !== '' ? $shift->unit_name : '';

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
