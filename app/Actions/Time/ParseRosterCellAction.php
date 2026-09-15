<?php

namespace App\Actions\Time;

use App\Data\Time\RosterCellData;
use App\Enums\RosterCellKind;
use App\Enums\ShiftTypeKind;
use App\Models\ShiftType;
use Illuminate\Support\Collection;

class ParseRosterCellAction
{
    /**
     * @param  Collection<int, ShiftType>|list<ShiftType>  $shiftTypes
     */
    public function handle(string $raw, Collection|array $shiftTypes): RosterCellData
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return new RosterCellData(RosterCellKind::Empty, '');
        }

        $types = Collection::make($shiftTypes);
        if (str_contains($trimmed, '-')) {
            return $this->parseFreeTime($trimmed);
        }

        return $this->parseCode($trimmed, $types);
    }

    /**
     * @param  Collection<int, ShiftType>  $types
     */
    private function parseCode(string $raw, Collection $types): RosterCellData
    {
        $code = ShiftType::normalizeCode($raw);
        $type = $types->first(fn (ShiftType $shiftType) => $shiftType->code === $code);

        if ($type === null || ! $type->is_active) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $raw,
                errorKey: 'time.schedule.errors.unknown_code',
            );
        }

        if ($type->kind->isAbsence()) {
            return new RosterCellData(
                kind: RosterCellKind::Absence,
                raw: $raw,
                code: $type->code,
                shiftTypeId: (int) $type->id,
                startTime: null,
                endTime: null,
                breakMinutes: 0,
                shiftTypeKind: $type->kind,
            );
        }

        return new RosterCellData(
            kind: RosterCellKind::ShiftType,
            raw: $raw,
            code: $type->code,
            shiftTypeId: (int) $type->id,
            startTime: ShiftType::formatTime($type->start_time),
            endTime: ShiftType::formatTime($type->end_time),
            breakMinutes: (int) $type->break_minutes,
            shiftTypeKind: ShiftTypeKind::Work,
        );
    }

    private function parseFreeTime(string $raw): RosterCellData
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $raw, $matches)) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $raw,
                errorKey: 'time.schedule.errors.invalid_time',
            );
        }

        $startHour = (int) $matches[1];
        $startMinute = (int) $matches[2];
        $endHour = (int) $matches[3];
        $endMinute = (int) $matches[4];

        if (
            $startHour > 23 || $endHour > 23
            || $startMinute > 59 || $endMinute > 59
        ) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $raw,
                errorKey: 'time.schedule.errors.invalid_time',
            );
        }

        $start = sprintf('%02d:%02d', $startHour, $startMinute);
        $end = sprintf('%02d:%02d', $endHour, $endMinute);
        $startMinutes = ShiftType::timeToMinutes($start);
        $endMinutes = ShiftType::timeToMinutes($end);

        if ($endMinutes <= $startMinutes) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $raw,
                errorKey: 'time.schedule.errors.night_not_allowed',
            );
        }

        return new RosterCellData(
            kind: RosterCellKind::FreeTime,
            raw: $raw,
            startTime: $start,
            endTime: $end,
            breakMinutes: 0,
            shiftTypeKind: ShiftTypeKind::Work,
        );
    }
}
