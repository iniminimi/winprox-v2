<?php

namespace App\Support\Time;

use App\Enums\ShiftTypeKind;
use App\Exceptions\RosterValidationException;
use App\Models\ShiftType;
use App\Models\Unit;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Support\Collection;

/**
 * Gedeelde blok-normalisatie voor rooster-schrijfpaden: shift-type-resolutie,
 * tijdformaat, unit-regels en notitie-afkapping. Gebruikt door
 * SavePlannedDayAction en CorrectPlannedShiftAction zodat gewone saves en
 * geauditeerde correcties identieke regels volgen.
 */
final class RosterBlockNormalizer
{
    /**
     * @param  array{id?: int|null, shift_type_id?: int|null, start_time?: ?string, end_time?: ?string, break_minutes?: int|null, unit_id?: int|null, description?: ?string}  $block
     * @param  Collection<int, ShiftType>  $types
     * @param  Collection<int, Unit>  $units
     * @return array{shift_type_id: ?int, kind: string, unit_id: ?int, unit_code: ?string, unit_name: ?string, location_id: ?int, start_time: ?string, end_time: ?string, break_minutes: int, description: ?string}
     */
    public static function normalize(array $block, Collection $types, Collection $units, int $index): array
    {
        $typeId = $block['shift_type_id'] ?? null;
        $typeId = $typeId !== null && $typeId !== '' ? (int) $typeId : null;

        $resolved = [
            'shift_type_id' => null,
            'kind' => ShiftTypeKind::Work->value,
            'start_time' => null,
            'end_time' => null,
            'break_minutes' => (int) ($block['break_minutes'] ?? 0),
            'description' => trim((string) ($block['description'] ?? '')) !== ''
                ? mb_substr(trim((string) $block['description']), 0, TextDescriptionLimits::MAX)
                : null,
        ];

        if ($typeId !== null) {
            /** @var ?ShiftType $type */
            $type = $types->first(fn (ShiftType $shiftType) => (int) $shiftType->id === $typeId);
            if (! $type instanceof ShiftType || ! $type->is_active) {
                throw new RosterValidationException(
                    'time.schedule.errors.unknown_code',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.unknown_code']],
                );
            }
            $resolved['shift_type_id'] = $typeId;
            $resolved['kind'] = $type->kind->value;
            if ($type->kind->isWork()) {
                $resolved['start_time'] = ShiftType::formatTime($type->start_time);
                $resolved['end_time'] = ShiftType::formatTime($type->end_time);
                $resolved['break_minutes'] = (int) $type->break_minutes;
            }
        } else {
            $start = trim((string) ($block['start_time'] ?? ''));
            $end = trim((string) ($block['end_time'] ?? ''));
            if (
                ! preg_match('/^\d{2}:\d{2}$/', $start)
                || ! preg_match('/^\d{2}:\d{2}$/', $end)
            ) {
                throw new RosterValidationException(
                    'time.schedule.errors.invalid_time',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.invalid_time']],
                );
            }
            if (ShiftType::timeToMinutes($end) <= ShiftType::timeToMinutes($start)) {
                throw new RosterValidationException(
                    'time.schedule.errors.night_not_allowed',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.night_not_allowed']],
                );
            }
            $resolved['start_time'] = $start;
            $resolved['end_time'] = $end;
        }

        $unitId = $block['unit_id'] ?? null;
        $unitId = $unitId !== null && $unitId !== '' ? (int) $unitId : null;

        if ($resolved['kind'] !== ShiftTypeKind::Work->value && $unitId !== null) {
            throw new RosterValidationException(
                'time.schedule.errors.absence_has_unit',
                cells: [['index' => $index, 'error' => 'time.schedule.errors.absence_has_unit']],
            );
        }

        $resolved['unit_id'] = null;
        $resolved['unit_code'] = null;
        $resolved['unit_name'] = null;
        $resolved['location_id'] = null;

        if ($unitId !== null) {
            /** @var ?Unit $unit */
            $unit = $units->first(fn (Unit $candidate) => (int) $candidate->id === $unitId);
            if (! $unit instanceof Unit) {
                throw new RosterValidationException(
                    'time.schedule.errors.unknown_unit',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.unknown_unit']],
                );
            }
            $resolved['unit_id'] = $unitId;
            $resolved['unit_code'] = Unit::normalizeRosterCode((string) $unit->roster_code);
            $resolved['unit_name'] = (string) $unit->name;
            $resolved['location_id'] = (int) $unit->location_id;
        }

        return $resolved;
    }
}
