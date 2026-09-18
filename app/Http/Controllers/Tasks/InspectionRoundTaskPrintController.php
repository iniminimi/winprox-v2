<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class InspectionRoundTaskPrintController
{
    public function __invoke(Task $task, RoundTaskCompletionAction $completion): View
    {
        Gate::authorize('view', $task);

        $task->loadMissing(['issue.location', 'issue.roundStops', 'team']);

        abort_unless($task->issue?->isInspectionRound(), 404);

        $tenant = Tenant::query()->findOrFail((int) $task->tenant_id);

        return view('reports.print-inspection-round-task', [
            'tenant' => $tenant,
            'task' => $task,
            'progress' => $completion->progress($task),
        ]);
    }
}
