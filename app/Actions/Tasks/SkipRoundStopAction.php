<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\StartTaskAction;
use App\Models\Task;
use App\Models\TaskRoundStopSkip;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkipRoundStopAction
{
    public function __construct(
        private RoundTaskCompletionAction $completion,
        private StartTaskAction $startTask,
        private CompleteTaskAction $completeTask,
    ) {}

    public function handle(Task $task, int $unitId, string $reason, Worker $worker): Task
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'skipReason' => [__('portal.round.errors.skip_reason_required')],
            ]);
        }

        $task->loadMissing('issue.roundStops');
        $stopUnitIds = $task->issue?->roundStops->pluck('unit_id')->map(fn ($id) => (int) $id)->all() ?? [];

        if (! in_array($unitId, $stopUnitIds, true)) {
            throw ValidationException::withMessages([
                'skipReason' => [__('portal.round.errors.not_a_stop')],
            ]);
        }

        if (! $this->completion->isNextOpenStop($task, $unitId)) {
            $nextName = $this->completion->progress($task)['next_unit_name'] ?? null;
            throw ValidationException::withMessages([
                'skipReason' => [__('portal.round.errors.not_next_stop', [
                    'name' => $nextName ?? '—',
                ])],
            ]);
        }

        if ((int) $worker->internal_team_id !== (int) $task->internal_team_id) {
            throw ValidationException::withMessages([
                'skipReason' => [__('portal.worker.errors.no_permission')],
            ]);
        }

        return DB::transaction(function () use ($task, $unitId, $reason, $worker) {
            TaskRoundStopSkip::query()->updateOrCreate(
                [
                    'task_id' => $task->id,
                    'unit_id' => $unitId,
                ],
                [
                    'worker_id' => $worker->id,
                    'reason' => $reason,
                ],
            );

            $task = $task->fresh(['issue.roundStops', 'roundStopSkips']);

            if ($task->canStart()) {
                $this->startTask->handle($task, $worker);
                $task = $task->fresh(['issue.roundStops', 'roundStopSkips']);
            }

            if ($task->canComplete() && $this->completion->isComplete($task)) {
                $this->completeTask->handle(
                    $task,
                    $worker,
                    __('portal.round.task_complete_note'),
                );
            }

            return $task->fresh();
        });
    }
}
