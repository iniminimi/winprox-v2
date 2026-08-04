<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Actions\Tasks\StartTaskAction;
use App\Data\Units\RecordUnitCheckData;
use App\Enums\UnitCheckResult;
use App\Models\Task;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Unit check OK: single-unit taak eerst, daarna ronde-voortgang — in één transactie.
 */
class RecordOkUnitCheckAndApplyTasksAction
{
    public function __construct(
        private RecordUnitCheckAction $recordUnitCheck,
        private ResolveOpenUnitTaskForCheckAction $resolveOpenTask,
        private RoundTaskCompletionAction $roundCompletion,
        private StartTaskAction $startTask,
        private CompleteTaskAction $completeTask,
    ) {}

    public function handle(
        Unit $unit,
        RecordUnitCheckData $data,
        int $tenantId,
        Worker $worker,
    ): UnitCheck {
        if ((int) $unit->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'checkResult' => [__('portal.worker.errors.no_permission')],
            ]);
        }

        if (! $unit->allowsUnitChecks()) {
            throw ValidationException::withMessages([
                'checkResult' => [__('portal.worker.errors.no_permission')],
            ]);
        }

        if ($data->result !== UnitCheckResult::Ok) {
            throw ValidationException::withMessages([
                'checkResult' => [__('unit_checks.validation.result_invalid')],
            ]);
        }

        return DB::transaction(function () use ($unit, $data, $tenantId, $worker) {
            $single = $this->resolveOpenTask->handle($unit, $worker, prefer: 'single');
            $round = $this->resolveOpenTask->handle($unit, $worker, prefer: 'round');

            if ($single !== null && $round !== null && $single->id === $round->id) {
                $round = null;
            }

            $primary = $single ?? $round;
            $check = $this->recordUnitCheck->handle(
                unit: $unit,
                data: new RecordUnitCheckData(
                    result: $data->result,
                    checkedAt: $data->checkedAt,
                    source: $data->source,
                    latitude: $data->latitude,
                    longitude: $data->longitude,
                    taskId: $primary?->id,
                    issueId: $primary?->issue_id,
                    checklistItems: $data->checklistItems,
                    externalId: $data->externalId,
                ),
                tenantId: $tenantId,
                worker: $worker,
            );

            if ($single !== null) {
                $this->startAndCompleteSingle($single, $worker);
            }

            if ($round !== null) {
                if ($single !== null) {
                    // Zelfde scan telt ook voor de ronde (aparte check-rij, 4b-scoped op round task_id).
                    $this->recordUnitCheck->handle(
                        unit: $unit,
                        data: new RecordUnitCheckData(
                            result: $data->result,
                            checkedAt: $data->checkedAt,
                            source: $data->source,
                            latitude: $data->latitude,
                            longitude: $data->longitude,
                            taskId: $round->id,
                            issueId: $round->issue_id,
                            checklistItems: $data->checklistItems,
                        ),
                        tenantId: $tenantId,
                        worker: $worker,
                    );
                }

                $this->progressRound($round, $worker);
            }

            return $check;
        });
    }

    private function startAndCompleteSingle(Task $task, Worker $worker): void
    {
        if ($task->canStart()) {
            $this->startTask->handle($task, $worker);
            $task = $task->fresh();
        }

        if ($task?->canComplete()) {
            $this->completeTask->handle(
                $task,
                $worker,
                __('portal.unit_check.task_complete_note'),
            );
        }
    }

    private function progressRound(Task $task, Worker $worker): void
    {
        $task = $task->fresh(['issue.roundStops', 'roundStopSkips']);

        if ($task->canStart()) {
            $this->startTask->handle($task, $worker);
            $task = $task->fresh(['issue.roundStops', 'roundStopSkips']);
        }

        if ($task->canComplete() && $this->roundCompletion->isComplete($task)) {
            $this->completeTask->handle(
                $task,
                $worker,
                __('portal.round.task_complete_note'),
            );
        }
    }
}
