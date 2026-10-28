<?php

namespace App\Actions\Time;

use App\Data\Time\TimePresenceAttentionItem;
use App\Enums\HolidayAgreementEffect;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Enums\TimePresenceAttentionType;
use App\Models\BelgianPublicHoliday;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\TenantHolidayAgreement;
use App\Models\WorkShift;
use App\Support\Time\BelgianHolidayStandardScheme;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CollectAbsenceClockInAttentionAction
{
    /**
     * Shifts whose clock-in falls on leave, recup, sick leave, or a public holiday that is not a normal workday.
     *
     * @param  Collection<int, WorkShift>  $openShifts
     * @return Collection<int, TimePresenceAttentionItem>
     */
    public function handle(int $tenantId, Collection $openShifts, ?PlannedShiftStatus $plannedStatus = null): Collection
    {
        if ($openShifts->isEmpty()) {
            return collect();
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || ! $tenant->timeRosterEnabled()) {
            return collect();
        }

        $timezone = (string) config('app.timezone');
        $locals = [];
        foreach ($openShifts as $shift) {
            if ($shift->clock_in_at === null) {
                continue;
            }
            $locals[$shift->id] = $shift->clock_in_at->copy()->timezone($timezone);
        }
        if ($locals === []) {
            return collect();
        }

        $dates = collect($locals)->map(fn (CarbonInterface $at) => $at->toDateString())->unique()->values()->all();
        $workerIds = $openShifts->pluck('worker_id')->unique()->filter()->all();

        $planned = PlannedShift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $workerIds)
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate('work_date', $date);
                }
            })
            ->when($plannedStatus instanceof PlannedShiftStatus, fn ($query) => $query->where('status', $plannedStatus))
            ->whereIn('kind', [ShiftTypeKind::Leave, ShiftTypeKind::Recup, ShiftTypeKind::Sick])
            ->get()
            ->groupBy(fn (PlannedShift $row) => $row->worker_id.'|'.$row->work_date->toDateString());

        $holidayDates = BelgianPublicHoliday::query()
            ->whereIn('date', $dates)
            ->pluck('date')
            ->map(fn ($date) => $date instanceof CarbonInterface ? $date->toDateString() : (string) $date)
            ->flip();

        $agreements = TenantHolidayAgreement::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('holiday_date', $dates)
            ->get()
            ->keyBy(fn (TenantHolidayAgreement $row) => $row->holiday_date->toDateString());

        $items = collect();
        foreach ($openShifts as $shift) {
            $local = $locals[$shift->id] ?? null;
            if (! $local instanceof CarbonInterface) {
                continue;
            }
            $kind = $this->coveringAbsenceKind(
                $planned->get($shift->worker_id.'|'.$local->toDateString(), collect()),
                $local,
            ) ?? $this->holidayKind($local->toDateString(), $holidayDates, $agreements);
            if ($kind === null) {
                continue;
            }
            $items->push(new TimePresenceAttentionItem(
                type: TimePresenceAttentionType::AbsenceClockIn,
                shift: $shift,
                absenceKind: $kind,
            ));
        }

        return $items->values();
    }

    /**
     * @param  Collection<int, PlannedShift>  $rows
     */
    private function coveringAbsenceKind(Collection $rows, CarbonInterface $clockIn): ?string
    {
        $best = null;
        $bestRank = 99;
        foreach ($rows as $row) {
            if (! $this->covers($row, $clockIn)) {
                continue;
            }
            $rank = match ($row->kind) {
                ShiftTypeKind::Sick => 0,
                ShiftTypeKind::Leave => 1,
                ShiftTypeKind::Recup => 2,
                default => 9,
            };
            if ($rank >= $bestRank) {
                continue;
            }
            $bestRank = $rank;
            $best = match ($row->kind) {
                ShiftTypeKind::Sick => 'sick',
                ShiftTypeKind::Leave => 'leave',
                ShiftTypeKind::Recup => 'recup',
                default => null,
            };
        }

        return $best;
    }

    private function covers(PlannedShift $planned, CarbonInterface $clockIn): bool
    {
        if ($planned->work_date->toDateString() !== $clockIn->toDateString()) {
            return false;
        }
        if ($planned->start_time === null && $planned->end_time === null) {
            return true;
        }
        if ($planned->start_time === null || $planned->end_time === null) {
            return false;
        }

        $timezone = $clockIn->getTimezone()->getName();
        $date = $clockIn->toDateString();
        $start = Carbon::parse($date.' '.$this->clockTime($planned->start_time), $timezone);
        $end = Carbon::parse($date.' '.$this->clockTime($planned->end_time), $timezone);
        if ($end->lessThanOrEqualTo($start)) {
            return false;
        }

        return $clockIn->greaterThanOrEqualTo($start) && $clockIn->lessThan($end);
    }

    private function clockTime(mixed $time): string
    {
        if ($time instanceof CarbonInterface) {
            return $time->format('H:i:s');
        }

        return (string) $time;
    }

    /**
     * @param  Collection<string, mixed>  $holidayDates
     * @param  Collection<string, TenantHolidayAgreement>  $agreements
     */
    private function holidayKind(string $date, Collection $holidayDates, Collection $agreements): ?string
    {
        if (! $holidayDates->has($date)) {
            return null;
        }

        $effect = $agreements->get($date)?->effect ?? BelgianHolidayStandardScheme::effectFor($date);

        return $effect === HolidayAgreementEffect::None ? null : 'holiday';
    }
}
