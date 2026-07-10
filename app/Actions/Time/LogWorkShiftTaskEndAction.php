<?php

namespace App\Actions\Time;

use App\Models\Task;
use App\Models\Worker;
use App\Models\WorkShiftTaskLog;
use Carbon\Carbon;

class LogWorkShiftTaskEndAction
{
    public function handle(Task $task, Worker $worker, ?Carbon $endedAt = null): int
    {
        if ($worker->id === null) {
            return 0;
        }

        $endedAt ??= now();

        return WorkShiftTaskLog::query()
            ->where('task_id', $task->id)
            ->where('worker_id', $worker->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => $endedAt]);
    }
}
