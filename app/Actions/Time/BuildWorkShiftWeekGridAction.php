<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Weekraster van geklokte diensten voor één uitvoerder: per dag de blokken
 * (in procent van 24 uur), pauzes binnen het blok en de geplande diensten.
 */
class BuildWorkShiftWeekGridAction
{
    private const DAY_MINUTES = 1440;

    public function __construct(private ResolveRosterWeekAction $resolveWeek) {}

    /**
     * @return array{
     *     weekStart: string,
     *     weekNumber: int,
     *     isCurrentWeek: bool,
     *     firstHour: int,
     *     workedMinutes: int,
     *     days: list<array{date: string, isToday: bool, nowPct: ?float, workedMinutes: int, allDayPlanned: list<string>, planned: list<array<string, mixed>>, blocks: list<array<string, mixed>>}>
     * }
     */
    public function handle(int $tenantId, int $workerId, string $cursor, ?int $clockPointId = null, bool $includeWeekends = true): array
    {
        [$monday, $sunday, $dates] = $this->resolveWeek->handle($cursor);
        if (! $includeWeekends) {
            $dates = array_values(array_filter(
                $dates,
                fn (string $date): bool => ! Carbon::parse($date)->isWeekend(),
            ));
        }
        $now = now();

        $shifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $workerId)
            ->when($clockPointId, fn ($q) => $q->where('clock_in_clock_point_id', $clockPointId))
            ->where('clock_in_at', '<=', $sunday)
            ->where(fn ($q) => $q->whereNull('clock_out_at')->orWhere('clock_out_at', '>=', $monday))
            ->with(['breaks', 'clockInClockPoint.location', 'clockOutClockPoint.location', 'team'])
            ->orderBy('clock_in_at')
            ->get();

        $days = [];
        foreach ($dates as $date) {
            $dayStart = Carbon::parse($date)->startOfDay();
            $isToday = $dayStart->isSameDay($now);
            $days[$date] = [
                'date' => $date,
                'isToday' => $isToday,
                'nowPct' => $isToday ? $this->pct($this->minuteOfDay($now, $dayStart)) : null,
                'workedMinutes' => 0,
                'allDayPlanned' => [],
                'planned' => [],
                'blocks' => [],
            ];
        }

        $firstMinute = null;

        foreach ($shifts as $shift) {
            $clockInDate = $shift->clock_in_at->toDateString();
            if (isset($days[$clockInDate])) {
                $days[$clockInDate]['workedMinutes'] += $shift->netWorkMinutes();
            }

            $end = $shift->clock_out_at ?? $now;
            if ($end->lessThanOrEqualTo($shift->clock_in_at)) {
                $end = $shift->clock_in_at->copy()->addMinute();
            }

            foreach ($dates as $date) {
                $dayStart = Carbon::parse($date)->startOfDay();
                $dayEnd = $dayStart->copy()->addDay();
                if ($shift->clock_in_at->greaterThanOrEqualTo($dayEnd) || $end->lessThanOrEqualTo($dayStart)) {
                    continue;
                }

                $startMinute = $this->minuteOfDay($shift->clock_in_at->max($dayStart), $dayStart);
                $endMinute = $end->greaterThanOrEqualTo($dayEnd) ? self::DAY_MINUTES : $this->minuteOfDay($end, $dayStart);
                $endMinute = max($endMinute, $startMinute + 1);
                $firstMinute = $firstMinute === null ? $startMinute : min($firstMinute, $startMinute);

                $inPlace = $shift->clockInClockPoint?->attendancePlaceLabel()
                    ?? __('time.shifts.clock_point_fallback');
                $outPlace = $shift->clockOutClockPoint?->attendancePlaceLabel() ?? $inPlace;
                $days[$date]['blocks'][] = [
                    'shift' => $shift,
                    'shiftId' => (int) $shift->id,
                    'open' => $shift->status->isOpen(),
                    'start' => $shift->clock_in_at->format('H:i'),
                    'end' => $shift->clock_out_at?->format('H:i'),
                    'place' => $inPlace,
                    'outPlace' => $outPlace,
                    'breakMinutes' => $shift->visibleBreakMinutes(),
                    'workedMinutes' => $shift->netWorkMinutes(),
                    'statusLabel' => $this->statusLabel($shift),
                    'manual' => $shift->isManuallyClockedIn(),
                    'topPct' => $this->pct($startMinute),
                    'heightPct' => $this->pct($endMinute - $startMinute),
                    'breaks' => $this->breakSegments($shift, $dayStart, $startMinute, $endMinute, $now),
                ];
            }
        }

        if (TimeModuleAccess::rosterEnabledForTenantId($tenantId)) {
            PlannedShift::query()
                ->where('tenant_id', $tenantId)
                ->where('worker_id', $workerId)
                ->whereBetween('work_date', [$monday->toDateString().' 00:00:00', $sunday->toDateString().' 23:59:59'])
                ->with('shiftType')
                ->orderBy('start_time')
                ->get()
                ->each(function (PlannedShift $planned) use (&$days, &$firstMinute): void {
                    $date = $planned->work_date->toDateString();
                    if (! isset($days[$date])) {
                        return;
                    }

                    $label = $planned->shiftType?->code ?: __('time.schedule.types.kinds.'.$planned->kind->value);
                    $start = ShiftType::formatTime($planned->start_time);
                    $end = ShiftType::formatTime($planned->end_time);
                    if ($start === '' || $end === '') {
                        $days[$date]['allDayPlanned'][] = $label;

                        return;
                    }

                    $startMinute = $this->clockMinutes($start);
                    $endMinute = $this->clockMinutes($end);
                    if ($endMinute <= $startMinute) {
                        $endMinute = self::DAY_MINUTES;
                    }
                    $firstMinute = $firstMinute === null ? $startMinute : min($firstMinute, $startMinute);

                    $days[$date]['planned'][] = [
                        'label' => $label.' '.$start.'–'.$end,
                        'topPct' => $this->pct($startMinute),
                        'heightPct' => $this->pct($endMinute - $startMinute),
                    ];
                });
        }

        return [
            'weekStart' => $monday->toDateString(),
            'weekNumber' => $monday->isoWeek(),
            'isCurrentWeek' => $monday->isSameWeek($now, Carbon::MONDAY),
            'firstHour' => $firstMinute === null ? 6 : max(0, intdiv($firstMinute, 60) - 1),
            'workedMinutes' => array_sum(array_column($days, 'workedMinutes')),
            'days' => array_values($days),
        ];
    }

    /**
     * @return list<array{topPct: float, heightPct: float}>
     */
    private function breakSegments(WorkShift $shift, Carbon $dayStart, int $blockStart, int $blockEnd, CarbonInterface $now): array
    {
        $span = $blockEnd - $blockStart;

        return $shift->breaks
            ->map(function (WorkBreak $break) use ($dayStart, $blockStart, $blockEnd, $span, $now): ?array {
                $end = $break->ended_at ?? $now;
                $from = max($blockStart, $this->clamp($this->minuteOfDay($break->started_at, $dayStart)));
                $to = min($blockEnd, $this->clamp($this->minuteOfDay($end, $dayStart)));
                if ($to <= $from) {
                    return null;
                }

                return [
                    'topPct' => round(($from - $blockStart) / $span * 100, 3),
                    'heightPct' => round(($to - $from) / $span * 100, 3),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function minuteOfDay(CarbonInterface $at, Carbon $dayStart): int
    {
        return (int) floor($dayStart->diffInMinutes($at, false));
    }

    private function clamp(int $minute): int
    {
        return max(0, min(self::DAY_MINUTES, $minute));
    }

    private function clockMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    private function pct(int $minutes): float
    {
        return round($this->clamp($minutes) / self::DAY_MINUTES * 100, 3);
    }

    private function statusLabel(WorkShift $shift): string
    {
        if ($shift->status === WorkShiftStatus::ForceClosed && $shift->clock_out_source === ClockSource::Auto) {
            return __('time.status.auto_closed');
        }

        return __('time.status.'.$shift->status->value);
    }
}
