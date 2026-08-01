<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Worker;

class ResolveOpenUnitTaskForCheckAction
{
    public function handle(Unit $unit, ?Worker $worker = null): ?Task
    {
        if ($worker === null || $worker->internal_team_id === null) {
            return null;
        }

        return Task::query()
            ->where('tenant_id', $unit->tenant_id)
            ->where('internal_team_id', $worker->internal_team_id)
            ->whereIn('status', TaskStatus::openValues())
            ->whereHas('issue', fn ($query) => $query
                ->where('unit_id', $unit->id)
                ->whereNotNull('approved_at'))
            ->orderByRaw('is_recurring_cycle desc')
            ->orderBy('due_at')
            ->orderBy('id')
            ->first();
    }
}
