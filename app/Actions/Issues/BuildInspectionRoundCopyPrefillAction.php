<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/**
 * Bouwt form-prefill om een bestaande inspectieronde te kopiëren.
 * Default: eenmalig, vervaldatum vandaag, geen uitvoerder (planner kiest).
 *
 * @return array{
 *     description: string,
 *     is_recurring: bool,
 *     recurrence_interval_value: int,
 *     recurrence_interval_unit: string,
 *     recurrence_lead_days: int,
 *     recurrence_first_due_date: string,
 *     round_stop_unit_ids: list<int>,
 *     internal_team_id: int|null,
 *     assigned_worker_id: null,
 *     task_priority: string,
 *     task_note: null
 * }
 */
class BuildInspectionRoundCopyPrefillAction
{
    public function handle(Issue $source): array
    {
        $source->loadMissing(['roundStops.unit.category', 'tasks']);

        if (! $source->isInspectionRound()) {
            throw ValidationException::withMessages([
                'issue' => [__('issues.errors.round_copy_not_round')],
            ]);
        }

        $stopIds = [];
        foreach ($source->roundStops->sortBy('sort_order') as $stop) {
            $unit = $stop->unit;
            if (! $unit instanceof Unit || ! $unit->is_active || ! $unit->allowsUnitChecks()) {
                continue;
            }
            $stopIds[] = (int) $unit->id;
        }

        $stopIds = array_values(array_unique($stopIds));

        if (count($stopIds) < 2) {
            throw ValidationException::withMessages([
                'round_stop_unit_ids' => [__('issues.errors.round_copy_stops_unavailable')],
            ]);
        }

        $lastTask = $source->tasks->sortByDesc('id')->first();
        $priority = $lastTask?->priority instanceof TaskPriority
            ? $lastTask->priority->value
            : TaskPriority::Prio3->value;

        return [
            'description' => (string) $source->description,
            'is_recurring' => false,
            'recurrence_interval_value' => max(1, (int) ($source->recurrence_interval_value ?? 1)),
            'recurrence_interval_unit' => $source->recurrence_interval_unit?->value ?? 'week',
            'recurrence_lead_days' => max(1, (int) ($source->recurrence_lead_days ?? 2)),
            'recurrence_first_due_date' => now()->toDateString(),
            'round_stop_unit_ids' => $stopIds,
            'internal_team_id' => $lastTask?->internal_team_id !== null
                ? (int) $lastTask->internal_team_id
                : null,
            'assigned_worker_id' => null,
            'task_priority' => $priority,
            'task_note' => null,
        ];
    }
}
