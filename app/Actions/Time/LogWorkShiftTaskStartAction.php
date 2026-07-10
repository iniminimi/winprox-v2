<?php

namespace App\Actions\Time;

use App\Models\Task;
use App\Models\Worker;
use App\Models\WorkShiftTaskLog;

class LogWorkShiftTaskStartAction
{
    public function __construct(
        private FindOpenWorkShiftForWorkerAction $findOpenShift,
    ) {}

    public function handle(Task $task, Worker $worker, ?\Carbon\Carbon $startedAt = null): ?WorkShiftTaskLog
    {
        if ((int) $worker->tenant_id !== (int) $task->tenant_id) {
            return null;
        }

        $shift = $this->findOpenShift->handle($worker);
        if ($shift === null) {
            return null;
        }

        $existing = WorkShiftTaskLog::query()
            ->where('work_shift_id', $shift->id)
            ->where('task_id', $task->id)
            ->whereNull('ended_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return WorkShiftTaskLog::create([
            'tenant_id' => $task->tenant_id,
            'work_shift_id' => $shift->id,
            'task_id' => $task->id,
            'worker_id' => $worker->id,
            'started_at' => $startedAt ?? now(),
        ]);
    }
}
