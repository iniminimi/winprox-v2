<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Worker;

class ResolveOpenUnitTaskForCheckAction
{
    public function __construct(
        private RoundTaskCompletionAction $roundCompletion,
    ) {}

    /**
     * @param  'any'|'single'|'round'  $prefer
     */
    public function handle(Unit $unit, ?Worker $worker = null, string $prefer = 'any'): ?Task
    {
        if ($worker === null || $worker->internal_team_id === null) {
            return null;
        }

        $base = Task::query()
            ->where('tenant_id', $unit->tenant_id)
            ->where('internal_team_id', $worker->internal_team_id)
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($query) => $query
                ->whereNotNull('approved_at')
                ->whereNull('esg_indicator_id'));

        if ($prefer === 'single' || $prefer === 'any') {
            $single = (clone $base)
                ->whereHas('issue', fn ($query) => $query->where('unit_id', $unit->id))
                ->orderByRaw('is_recurring_cycle desc')
                ->orderBy('due_at')
                ->orderBy('id')
                ->first();

            if ($single !== null || $prefer === 'single') {
                return $single;
            }
        }

        $round = (clone $base)
            ->with(['issue.roundStops', 'roundStopSkips'])
            ->whereHas('issue', fn ($query) => $query
                ->whereHas('roundStops', fn ($stops) => $stops->where('unit_id', $unit->id)))
            ->orderByRaw('is_recurring_cycle desc')
            ->orderBy('due_at')
            ->orderBy('id')
            ->first();

        if ($round === null) {
            return null;
        }

        // Fase 2: strikte volgorde — alleen de eerstvolgende open stop telt.
        if (! $this->roundCompletion->isNextOpenStop($round, (int) $unit->id)) {
            return null;
        }

        return $round;
    }

    /**
     * Open ronde-taak waar deze unit stop is, maar nog niet aan de beurt (voor UX-feedback).
     */
    public function findRoundWaitingOnEarlierStop(Unit $unit, Worker $worker): ?Task
    {
        if ($worker->internal_team_id === null) {
            return null;
        }

        $round = Task::query()
            ->where('tenant_id', $unit->tenant_id)
            ->where('internal_team_id', $worker->internal_team_id)
            ->whereIn('status', TaskStatus::openValues())
            ->with(['issue.roundStops.unit.translations', 'roundStopSkips'])
            ->whereHas('issue', fn ($query) => $query
                ->whereNotNull('approved_at')
                ->whereNull('esg_indicator_id')
                ->whereHas('roundStops', fn ($stops) => $stops->where('unit_id', $unit->id)))
            ->orderByRaw('is_recurring_cycle desc')
            ->orderBy('due_at')
            ->orderBy('id')
            ->first();

        if ($round === null) {
            return null;
        }

        if ($this->roundCompletion->isNextOpenStop($round, (int) $unit->id)) {
            return null;
        }

        if ($this->roundCompletion->isComplete($round)) {
            return null;
        }

        // Alleen melden als deze unit nog open is (niet al OK/skipped).
        if (! $this->roundCompletion->openStopUnitIds($round)->contains((int) $unit->id)) {
            return null;
        }

        return $round;
    }
}
