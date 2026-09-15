<?php

namespace App\Actions\Time;

use App\Data\Time\RosterCellData;
use App\Enums\RosterCellKind;
use App\Enums\ShiftTypeKind;
use App\Models\ShiftType;
use App\Models\Unit;
use Illuminate\Support\Collection;

class ParseRosterCellAction
{
    /**
     * @param  Collection<int, ShiftType>|list<ShiftType>  $shiftTypes
     * @param  Collection<int, Unit>|list<Unit>  $units
     */
    public function handle(string $raw, Collection|array $shiftTypes, Collection|array $units = []): RosterCellData
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return new RosterCellData(RosterCellKind::Empty, '');
        }

        $split = $this->splitDutyAndUnit($trimmed);
        if ($split === null) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $raw,
                errorKey: 'time.schedule.errors.unknown_code',
            );
        }

        [$duty, $unitToken] = $split;
        $types = Collection::make($shiftTypes);
        $parsed = str_contains($duty, '-')
            ? $this->parseFreeTime($duty, $raw)
            : $this->parseCode($duty, $types, $raw);

        if ($parsed->kind === RosterCellKind::Invalid || $unitToken === null) {
            return $parsed;
        }

        if ($parsed->kind === RosterCellKind::Absence) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $raw,
                errorKey: 'time.schedule.errors.absence_has_unit',
            );
        }

        return $this->withUnit($parsed, $unitToken, Collection::make($units), $raw);
    }

    /**
     * @return array{0: string, 1: ?string}|null
     */
    private function splitDutyAndUnit(string $raw): ?array
    {
        $normalized = str_replace(["\r\n", "\r", "\n"], '/', $raw);

        if (str_contains($normalized, '/')) {
            $parts = array_map('trim', explode('/', $normalized, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || str_contains($parts[1], '/')) {
                return null;
            }

            return [$parts[0], $parts[1]];
        }

        if (preg_match('/^(\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2})(?:\s+(\S+))?$/', $raw, $matches)) {
            $unit = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : null;

            return [$matches[1], $unit];
        }

        if (preg_match('/^(\S+)\s+(\S+)$/', $raw, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return [$raw, null];
    }

    /**
     * @param  Collection<int, ShiftType>  $types
     */
    private function parseCode(string $raw, Collection $types, string $original): RosterCellData
    {
        $code = ShiftType::normalizeCode($raw);
        $type = $types->first(fn (ShiftType $shiftType) => $shiftType->code === $code);

        if ($type === null || ! $type->is_active) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $original,
                errorKey: 'time.schedule.errors.unknown_code',
            );
        }

        if ($type->kind->isAbsence()) {
            return new RosterCellData(
                kind: RosterCellKind::Absence,
                raw: $original,
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
            raw: $original,
            code: $type->code,
            shiftTypeId: (int) $type->id,
            startTime: ShiftType::formatTime($type->start_time),
            endTime: ShiftType::formatTime($type->end_time),
            breakMinutes: (int) $type->break_minutes,
            shiftTypeKind: ShiftTypeKind::Work,
        );
    }

    private function parseFreeTime(string $raw, string $original): RosterCellData
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $raw, $matches)) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $original,
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
                raw: $original,
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
                raw: $original,
                errorKey: 'time.schedule.errors.night_not_allowed',
            );
        }

        return new RosterCellData(
            kind: RosterCellKind::FreeTime,
            raw: $original,
            startTime: $start,
            endTime: $end,
            breakMinutes: 0,
            shiftTypeKind: ShiftTypeKind::Work,
        );
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    private function withUnit(RosterCellData $parsed, string $token, Collection $units, string $original): RosterCellData
    {
        $code = Unit::normalizeRosterCode($token);
        if ($code === '' || strlen($code) > 8 || ! preg_match('/^[A-Z0-9]+$/', $code)) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $original,
                errorKey: 'time.schedule.errors.unknown_unit',
            );
        }

        $matches = $units->filter(
            fn (Unit $unit) => $unit->is_active && Unit::normalizeRosterCode((string) $unit->roster_code) === $code,
        )->values();

        if ($matches->count() === 0) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $original,
                errorKey: 'time.schedule.errors.unknown_unit',
            );
        }

        if ($matches->count() > 1) {
            return new RosterCellData(
                kind: RosterCellKind::Invalid,
                raw: $original,
                errorKey: 'time.schedule.errors.ambiguous_unit',
                ambiguousCount: $matches->count(),
            );
        }

        /** @var Unit $unit */
        $unit = $matches->first();

        return new RosterCellData(
            kind: $parsed->kind,
            raw: $original,
            code: $parsed->code,
            shiftTypeId: $parsed->shiftTypeId,
            startTime: $parsed->startTime,
            endTime: $parsed->endTime,
            breakMinutes: $parsed->breakMinutes,
            shiftTypeKind: $parsed->shiftTypeKind,
            unitId: (int) $unit->id,
            unitCode: $code,
            unitName: (string) $unit->name,
            locationId: (int) $unit->location_id,
        );
    }
}
