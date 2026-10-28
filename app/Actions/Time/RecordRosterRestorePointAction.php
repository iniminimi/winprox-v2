<?php

namespace App\Actions\Time;

use App\Enums\RosterRestorePointSource;
use App\Models\PlannedShift;
use App\Models\RosterRestorePoint;
use App\Models\ShiftType;
use App\Models\Tenant;

/**
 * Bewaart de roosterstand van vóór een wijziging. Geen herstel:
 * de aanroepende transactie beslist of het punt blijft staan.
 */
class RecordRosterRestorePointAction
{
    /**
     * @param  iterable<PlannedShift>  $shifts
     * @return list<array{
     *     planned_shift_id: int,
     *     worker_id: int,
     *     work_date: string,
     *     shift_type_id: ?int,
     *     shift_type_code: ?string,
     *     shift_type_label: ?string,
     *     kind: string,
     *     unit_id: ?int,
     *     unit_code: ?string,
     *     unit_name: ?string,
     *     location_id: ?int,
     *     start_time: ?string,
     *     end_time: ?string,
     *     break_minutes: int,
     *     description: ?string,
     *     status: string
     * }>
     */
    public function capture(iterable $shifts): array
    {
        $rows = [];
        foreach ($shifts as $shift) {
            $rows[] = $this->row($shift);
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['work_date'], $a['start_time'] ?? '', $a['planned_shift_id']]
                <=> [$b['work_date'], $b['start_time'] ?? '', $b['planned_shift_id']];
        });

        return $rows;
    }

    /**
     * @param  list<array{worker_id: int, date: string}>  $scope
     * @param  list<array<string, mixed>>  $shifts  resultaat van capture(), vóór de mutatie
     */
    public function handle(
        Tenant $tenant,
        ?int $actorUserId,
        RosterRestorePointSource $source,
        array $scope,
        array $shifts,
    ): ?RosterRestorePoint {
        $normalized = $this->scope($scope);
        if ($normalized === []) {
            return null;
        }

        $allowed = [];
        foreach ($normalized as $item) {
            $allowed[$item['worker_id'].':'.$item['date']] = true;
        }

        $rows = [];
        foreach ($shifts as $row) {
            $key = ((int) $row['worker_id']).':'.(string) $row['work_date'];
            if (isset($allowed[$key])) {
                $rows[] = $row;
            }
        }

        return RosterRestorePoint::query()->create([
            'tenant_id' => (int) $tenant->id,
            'actor_user_id' => $actorUserId,
            'source' => $source,
            'scope' => $normalized,
            'shifts' => $rows,
        ]);
    }

    /**
     * @param  list<array{worker_id: int, date: string}>  $scope
     * @return list<array{worker_id: int, date: string}>
     */
    private function scope(array $scope): array
    {
        $unique = [];
        foreach ($scope as $item) {
            $workerId = (int) $item['worker_id'];
            $date = (string) $item['date'];
            $unique[$workerId.':'.$date] = ['worker_id' => $workerId, 'date' => $date];
        }

        $rows = array_values($unique);
        usort($rows, fn (array $a, array $b): int => [$a['worker_id'], $a['date']] <=> [$b['worker_id'], $b['date']]);

        return $rows;
    }

    /**
     * @return array{
     *     planned_shift_id: int,
     *     worker_id: int,
     *     work_date: string,
     *     shift_type_id: ?int,
     *     shift_type_code: ?string,
     *     shift_type_label: ?string,
     *     kind: string,
     *     unit_id: ?int,
     *     unit_code: ?string,
     *     unit_name: ?string,
     *     location_id: ?int,
     *     start_time: ?string,
     *     end_time: ?string,
     *     break_minutes: int,
     *     description: ?string,
     *     status: string
     * }
     */
    private function row(PlannedShift $shift): array
    {
        $type = $shift->relationLoaded('shiftType') ? $shift->shiftType : null;

        return [
            'planned_shift_id' => (int) $shift->id,
            'worker_id' => (int) $shift->worker_id,
            'work_date' => $shift->work_date->toDateString(),
            'shift_type_id' => $shift->shift_type_id !== null ? (int) $shift->shift_type_id : null,
            'shift_type_code' => $type?->code,
            'shift_type_label' => $type?->label,
            'kind' => $shift->kind->value,
            'unit_id' => $shift->unit_id !== null ? (int) $shift->unit_id : null,
            'unit_code' => $shift->unit_code,
            'unit_name' => $shift->unit_name,
            'location_id' => $shift->location_id !== null ? (int) $shift->location_id : null,
            'start_time' => $this->time($shift->start_time),
            'end_time' => $this->time($shift->end_time),
            'break_minutes' => (int) $shift->break_minutes,
            'description' => $shift->description,
            'status' => $shift->status->value,
        ];
    }

    private function time(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $formatted = ShiftType::formatTime($value);

        return $formatted === '' ? null : $formatted;
    }
}
