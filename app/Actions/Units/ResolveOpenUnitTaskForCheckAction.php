<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Worker;

class ResolveOpenUnitTaskForCheckAction
{
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

        return (clone $base)
            ->whereHas('issue', fn ($query) => $query
                ->whereHas('roundStops', fn ($stops) => $stops->where('unit_id', $unit->id)))
            ->orderByRaw('is_recurring_cycle desc')
            ->orderBy('due_at')
            ->orderBy('id')
            ->first();
    }
}
