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
     * Attendance per worker×dag, per gepland blok.
     *
     * Matchingregel (vastgelegd, getest via randgevallenmatrix):
     * - Een klok-sessie "raakt" een blok als hun overlap > 0 min is.
     * - Per blok tellen ALLE rakende sessies mee: eerste inklok bepaalt de
     *   effectieve start, laatste uitklok het effectieve einde. Gaten binnen
     *   het blok (pauze / uit- en weer inklokken) zijn géén afwijking.
     * - Een sessie die geen enkel werkblok raakt is `unplanned`.
     * - Sessie-minuten buiten alle blokken terwijl de sessie wél blokken raakt
     *   tellen als `gap_minutes` ("extra tijd tussen blokken") op dag-niveau.
     * - Afwezigheidsblokken (omgezette rijen of verlof) doen niet mee: geen
     *   missing, geen overlap.
     *
     * @param  Collection<int, PlannedShift>  $shifts
     * @param  list<int>  $workerIds
     * @param  list<string>  $dates
     * @return array<string, array{status: string, blocks: array<int, string>, gap_minutes: int, unplanned_sessions: int}>
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
        $tolerance = $this->toleranceMinutes();

        $punches = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $workerIds)
            ->where('clock_in_at', '>=', $from)
            ->where('clock_in_at', '<=', $to)
            ->orderBy('clock_in_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WorkShift $shift) => $shift->worker_id.':'.$shift->clock_in_at->toDateString());

        $plannedByKey = [];
        foreach ($shifts as $shift) {
            $key = $shift->worker_id.':'.$shift->work_date->toDateString();
            $plannedByKey[$key][] = $shift;
        }

        $result = [];
        foreach ($workerIds as $workerId) {
            foreach ($dates as $date) {
                if ($date > $today) {
                    continue;
                }

                $key = $workerId.':'.$date;
                $dayShifts = collect($plannedByKey[$key] ?? [])
                    ->filter(fn (PlannedShift $shift) => $shift->status->isPublished());
                $dayPunches = $punches->get($key, collect());

                $entry = $this->dayAttendance($dayShifts, $dayPunches, $date, $now, $tolerance);

                if ($entry['status'] !== RosterAttendanceStatus::None->value) {
                    $result[$key] = $entry;
                }
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, PlannedShift>  $dayShifts  published rows only
     * @param  Collection<int, WorkShift>  $dayPunches
     * @return array{status: string, blocks: array<int, string>, gap_minutes: int, unplanned_sessions: int}
     */
    private function dayAttendance(
        Collection $dayShifts,
        Collection $dayPunches,
        string $date,
        Carbon $now,
        int $tolerance,
    ): array {
        $workBlocks = $dayShifts
            ->filter(fn (PlannedShift $shift) => $shift->kind->isWork())
            ->sortBy(fn (PlannedShift $shift) => ShiftType::timeToMinutes((string) $shift->start_time))
            ->values();
        $hasAbsence = $dayShifts->contains(fn (PlannedShift $shift) => $shift->kind->isAbsence());

        $sessions = $dayPunches
            ->map(fn (WorkShift $punch) => $this->sessionInterval($punch))
            ->values();

        if ($workBlocks->isEmpty()) {
            return [
                'status' => $sessions->isNotEmpty()
                    ? RosterAttendanceStatus::Unplanned->value
                    : ($hasAbsence ? RosterAttendanceStatus::Ok->value : RosterAttendanceStatus::None->value),
                'blocks' => [],
                'gap_minutes' => 0,
                'unplanned_sessions' => $sessions->count(),
            ];
        }

        $blockWindows = $workBlocks
            ->map(fn (PlannedShift $shift) => [
                ShiftType::timeToMinutes((string) $shift->start_time),
                ShiftType::timeToMinutes((string) $shift->end_time),
            ])
            ->all();

        $blocks = [];
        foreach ($workBlocks as $block) {
            $blocks[(int) $block->id] = $this->blockStatus($block, $sessions, $blockWindows, $date, $now, $tolerance);
        }

        $unplanned = 0;
        $gapMinutes = 0;
        foreach ($sessions as $session) {
            $touchesBlock = false;
            foreach ($blockWindows as [$start, $end]) {
                if ($session[0] < $end && $start < $session[1]) {
                    $touchesBlock = true;
                    break;
                }
            }
            if (! $touchesBlock) {
                $unplanned++;
            } elseif (! $session[2]) {
                // Alleen gesloten sessies leveren meetbare "extra tijd" op;
                // een open sessie loopt nog en heeft geen definitief einde.
                $gapMinutes += $this->uncoveredMinutes($session, $blockWindows);
            }
        }

        return [
            'status' => $this->aggregate($blocks, $unplanned, $gapMinutes, $tolerance),
            'blocks' => $blocks,
            'gap_minutes' => $gapMinutes,
            'unplanned_sessions' => $unplanned,
        ];
    }

    /**
     * @param  Collection<int, array{0: int, 1: int}>  $sessions
     */
    private function blockStatus(
        PlannedShift $block,
        Collection $sessions,
        array $blockWindows,
        string $date,
        Carbon $now,
        int $tolerance,
    ): string {
        if ($block->start_time === null || $block->end_time === null) {
            return RosterAttendanceStatus::Deviation->value;
        }

        $start = ShiftType::timeToMinutes($block->start_time);
        $end = ShiftType::timeToMinutes($block->end_time);

        $covering = $sessions->filter(
            fn (array $session) => $session[0] < $end && $start < $session[1],
        )->values();

        if ($covering->isEmpty()) {
            return $this->plannedEndPassed($block, $date, $now)
                ? RosterAttendanceStatus::Missing->value
                : RosterAttendanceStatus::None->value;
        }

        $firstIn = $covering->min(fn (array $session) => $session[0]);
        $hasOpen = $covering->contains(fn (array $session) => $session[2]);
        $lastOut = $covering->max(fn (array $session) => $session[1]);

        // Een sessie die al liep toen het blok begon en ook een ander blok
        // raakt (brug vanuit een vroeger blok) voldoet aan de start.
        $bridgesIn = $covering->contains(
            fn (array $session) => $session[0] <= $start
                && $this->touchesOtherBlock($session, $blockWindows, [$start, $end]),
        );
        if (! $bridgesIn && ! $this->withinAttendanceWindow($firstIn, $start, $tolerance)) {
            return RosterAttendanceStatus::Deviation->value;
        }

        if ($hasOpen) {
            return $this->plannedClockOutWindowPassed($block, $date, $now, $tolerance)
                ? RosterAttendanceStatus::Deviation->value
                : RosterAttendanceStatus::Ok->value;
        }

        if ($this->withinAttendanceWindow($lastOut, $end, $tolerance)) {
            return RosterAttendanceStatus::Ok->value;
        }

        if ($lastOut < $end - $tolerance) {
            return RosterAttendanceStatus::Deviation->value;
        }

        // Uitklok ver na blok-einde: ok als dezelfde sessie doorloopt naar een
        // volgend blok (brug) — de doorgeklokte tussen-tijd telt als gap op
        // dag-niveau. Anders blijft het een afwijking (te laat uitgeklokt).
        $bridges = $covering->contains(fn (array $session) => $session[1] > $end && $this->touchesOtherBlock($session, $blockWindows, [$start, $end]));

        return $bridges ? RosterAttendanceStatus::Ok->value : RosterAttendanceStatus::Deviation->value;
    }

    /**
     * Raakt deze sessie een ander werkblok dan het eigen venster?
     *
     * @param  array{0: int, 1: int}  $session
     * @param  list<array{0: int, 1: int}>  $blockWindows
     * @param  array{0: int, 1: int}  $ownWindow
     */
    private function touchesOtherBlock(array $session, array $blockWindows, array $ownWindow): bool
    {
        foreach ($blockWindows as [$start, $end]) {
            if ($start === $ownWindow[0] && $end === $ownWindow[1]) {
                continue;
            }
            if ($session[0] < $end && $start < $session[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $blocks
     */
    private function aggregate(array $blocks, int $unplannedSessions, int $gapMinutes, int $tolerance): string
    {
        $statuses = array_filter(
            $blocks,
            fn (string $status) => $status !== RosterAttendanceStatus::None->value,
        );

        if (in_array(RosterAttendanceStatus::Missing->value, $statuses, true)) {
            return RosterAttendanceStatus::Missing->value;
        }
        if (in_array(RosterAttendanceStatus::Deviation->value, $statuses, true)) {
            return RosterAttendanceStatus::Deviation->value;
        }
        if ($unplannedSessions > 0) {
            return RosterAttendanceStatus::Unplanned->value;
        }
        if ($gapMinutes > $tolerance) {
            return RosterAttendanceStatus::Gap->value;
        }

        return $statuses !== [] ? RosterAttendanceStatus::Ok->value : RosterAttendanceStatus::None->value;
    }

    /**
     * Session as [start-minute, end-minute, is-open] within the clock-in day.
     * End is clipped to the day boundary; an open session runs to +∞ so it
     * still overlaps and keeps covering its block.
     *
     * @return array{0: int, 1: int, 2: bool}
     */
    private function sessionInterval(WorkShift $punch): array
    {
        $in = $this->punchMinutes($punch->clock_in_at);

        if ($punch->clock_out_at === null) {
            return [$in, 24 * 60 + 1, true];
        }

        $out = $this->punchMinutes($punch->clock_out_at);
        if ($punch->clock_out_at->isAfter($punch->clock_in_at->copy()->endOfDay())) {
            $out = 24 * 60;
        }

        return [$in, max($out, $in + 1), false];
    }

    /**
     * Session-minuten die buiten alle blokvensters vallen.
     *
     * @param  array{0: int, 1: int}  $session
     * @param  list<array{0: int, 1: int}>  $blockWindows
     */
    private function uncoveredMinutes(array $session, array $blockWindows): int
    {
        // Blokvensters overlappen elkaar nooit (save-time validatie),
        // dus intersecties mogen gewoon opgeteld worden.
        $sessionEnd = min($session[1], 24 * 60);
        $covered = 0;
        foreach ($blockWindows as [$start, $end]) {
            $overlap = min($sessionEnd, $end) - max($session[0], $start);
            if ($overlap > 0) {
                $covered += $overlap;
            }
        }

        return max(0, $sessionEnd - $session[0] - $covered);
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
