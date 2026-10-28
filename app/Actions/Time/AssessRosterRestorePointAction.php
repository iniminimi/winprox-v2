<?php

namespace App\Actions\Time;

use App\Data\Time\RosterRestoreAssessment;
use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Support\Time\RosterHistoryLock;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Beoordeelt een herstelpunt tegen de huidige stand.
 * Schrijft niets.
 */
class AssessRosterRestorePointAction
{
    private const FIELDS = [
        'worker_id',
        'work_date',
        'shift_type_id',
        'kind',
        'unit_id',
        'unit_code',
        'unit_name',
        'location_id',
        'start_time',
        'end_time',
        'break_minutes',
        'description',
        'status',
    ];

    public function __construct(
        private RecordRosterRestorePointAction $recordRestorePoint,
    ) {}

    /**
     * @param  list<array{worker_id: int, date: string}>  $scope
     * @param  list<array<string, mixed>>  $savedInScope
     * @param  Collection<int, PlannedShift>  $locked
     */
    public function handle(int $tenantId, array $scope, array $savedInScope, Collection $locked): RosterRestoreAssessment
    {
        $scopeKeys = [];
        $workerIds = [];
        $dates = [];
        foreach ($scope as $item) {
            $workerId = (int) $item['worker_id'];
            $date = (string) $item['date'];
            $scopeKeys[$workerId.':'.$date] = ['worker_id' => $workerId, 'date' => $date];
            $workerIds[$workerId] = $workerId;
            $dates[$date] = $date;
        }

        $blocks = [];
        foreach ($scopeKeys as $item) {
            if (RosterHistoryLock::isLockedDate($item['date'])) {
                $blocks[] = [
                    'code' => 'locked_day',
                    'worker_id' => $item['worker_id'],
                    'date' => $item['date'],
                ];
            }
        }

        $byId = $locked->keyBy(fn (PlannedShift $shift): int => (int) $shift->id);
        foreach ($savedInScope as $row) {
            $found = $byId->get((int) $row['planned_shift_id']);
            if (! $found instanceof PlannedShift) {
                continue;
            }
            $samePlace = (int) $found->tenant_id === $tenantId
                && (int) $found->worker_id === (int) $row['worker_id']
                && $found->work_date->toDateString() === (string) $row['work_date'];
            if ($samePlace) {
                continue;
            }
            $blocks[] = [
                'code' => 'restore_id_elsewhere',
                'worker_id' => (int) $row['worker_id'],
                'date' => (string) $row['work_date'],
            ];
        }

        $currentModels = $locked->filter(function (PlannedShift $shift) use ($tenantId, $scopeKeys): bool {
            if ((int) $shift->tenant_id !== $tenantId) {
                return false;
            }
            $key = ((int) $shift->worker_id).':'.$shift->work_date->toDateString();

            return isset($scopeKeys[$key]);
        });
        $current = [];
        foreach ($this->recordRestorePoint->capture($currentModels) as $row) {
            $current[(int) $row['planned_shift_id']] = $row;
        }
        $saved = [];
        foreach ($savedInScope as $row) {
            $saved[(int) $row['planned_shift_id']] = $row;
        }

        $differences = [];
        foreach ($current as $id => $row) {
            if (! isset($saved[$id])) {
                $differences[] = $this->difference('added', $row, []);

                continue;
            }
            $fields = $this->changedFields($saved[$id], $row);
            if ($fields !== []) {
                $differences[] = $this->difference('changed', $row, $fields);
            }
        }
        foreach ($saved as $id => $row) {
            if (! isset($current[$id])) {
                $differences[] = $this->difference('removed', $row, []);
            }
        }
        usort($differences, fn (array $a, array $b): int => [$a['date'], $a['planned_shift_id'], $a['type']]
            <=> [$b['date'], $b['planned_shift_id'], $b['type']]);

        $changedDays = [];
        foreach ($differences as $difference) {
            $changedDays[$difference['worker_id'].':'.$difference['date']] = true;
        }

        $warnings = [];
        if ($workerIds !== [] && $dates !== []) {
            $from = min($dates);
            $to = max($dates);
            $requests = AbsenceRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('worker_id', array_values($workerIds))
                ->whereIn('status', [AbsenceRequestStatus::Pending->value, AbsenceRequestStatus::Approved->value])
                ->whereDate('date_from', '<=', $to)
                ->whereDate('date_to', '>=', $from)
                ->get();

            foreach ($requests as $request) {
                foreach ($scopeKeys as $item) {
                    if ((int) $request->worker_id !== $item['worker_id']) {
                        continue;
                    }
                    $day = Carbon::parse($item['date'])->startOfDay();
                    if ($day->lt($request->date_from->copy()->startOfDay()) || $day->gt($request->date_to->copy()->startOfDay())) {
                        continue;
                    }
                    if (! isset($changedDays[$item['worker_id'].':'.$item['date']])) {
                        continue;
                    }
                    if ($request->status === AbsenceRequestStatus::Approved) {
                        $blocks[] = [
                            'code' => 'approved_absence',
                            'worker_id' => $item['worker_id'],
                            'date' => $item['date'],
                        ];

                        continue;
                    }
                    $warnings[] = [
                        'code' => 'pending_absence',
                        'worker_id' => $item['worker_id'],
                        'date' => $item['date'],
                    ];
                }
            }
        }

        return new RosterRestoreAssessment($blocks, $warnings, $differences);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $fields
     * @return array{type: string, worker_id: int, date: string, planned_shift_id: int, fields: list<string>}
     */
    private function difference(string $type, array $row, array $fields): array
    {
        return [
            'type' => $type,
            'worker_id' => (int) $row['worker_id'],
            'date' => (string) $row['work_date'],
            'planned_shift_id' => (int) $row['planned_shift_id'],
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $saved
     * @param  array<string, mixed>  $current
     * @return list<string>
     */
    private function changedFields(array $saved, array $current): array
    {
        $fields = [];
        foreach (self::FIELDS as $field) {
            if ($this->value($saved, $field) !== $this->value($current, $field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function value(array $row, string $field): mixed
    {
        $value = $row[$field] ?? null;
        if (in_array($field, ['worker_id', 'shift_type_id', 'unit_id', 'location_id', 'break_minutes'], true)) {
            return $value === null || $value === '' ? ($field === 'break_minutes' ? 0 : null) : (int) $value;
        }
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
