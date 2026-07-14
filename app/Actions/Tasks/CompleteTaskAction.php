<?php

namespace App\Actions\Tasks;

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Actions\Issues\RecalculateIssueStatusAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCompleted;
use App\Models\IssueUpdate;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;
use App\Actions\Time\LogWorkShiftTaskEndAction;
use App\Support\Audit\AuditRecorder;
use App\Support\IssuePhotoStorage;
use App\Support\Tasks\TaskIssueApproval;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Worker handelt een taak af: optionele notitie + tot 4 foto's worden vastgelegd
 * als IssueUpdate(s) (met foto's), taak → Afgehandeld (+ completed_at), en de
 * meldingstatus wordt herberekend (rollup → Gesloten als geen open taken meer).
 */
class CompleteTaskAction
{
    public function __construct(
        private IssuePhotoStorage $storage,
        private AuditRecorder $audit,
        private RecalculateIssueStatusAction $recalculateIssueStatus,
        private LogWorkShiftTaskEndAction $logShiftTaskEnd,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(
        Task $task,
        ?Worker $worker = null,
        ?string $note = null,
        array $photos = [],
        ?\Carbon\Carbon $clientTimestamp = null,
        ?RecordEsgMeasurementData $esgMeasurement = null,
    ): Task {
        TaskIssueApproval::assertTaskMutable($task);

        if (! $task->canComplete()) {
            return $task;
        }

        $issue = $task->issue;
        $this->recordRequiredEsgMeasurement($task, $esgMeasurement, $worker);
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        if ($note !== null && $worker !== null) {
            $issue->updates()->create([
                'worker_id' => $worker->id,
                'kind' => 'worker_note',
                'description' => $note,
            ]);
        }

        $files = array_values(array_filter($photos, fn ($photo) => $photo instanceof UploadedFile));
        if ($files !== [] && $worker !== null) {
            Tenant::query()->findOrFail($issue->tenant_id)->assertCanAddPhotos(count($files));

            /** @var IssueUpdate $update */
            $update = $issue->updates()->create([
                'worker_id' => $worker->id,
                'kind' => 'worker_photos',
                'description' => null,
            ]);

            foreach ($files as $photo) {
                $issue->photos()->create([
                    'issue_update_id' => $update->id,
                    'path' => $this->storage->storePrecompressedCopy($photo),
                ]);
            }
        }

        $task->update([
            'status' => TaskStatus::Done,
            'started_at' => $task->started_at ?? ($clientTimestamp ?? now()),
            'completed_at' => $clientTimestamp ?? now(),
        ]);

        if ($worker !== null) {
            $fresh = $task->fresh();
            $this->logShiftTaskEnd->handle($fresh, $worker, $fresh->completed_at);
        }

        $this->audit->record(
            userId: null, // Workers are not users
            tenantId: $task->tenant_id,
            action: 'task_completed',
            modelType: Task::class,
            modelId: $task->id,
            payload: [
                'task_id' => $task->id,
                'worker_id' => $worker?->id,
                'client_timestamp' => $clientTimestamp?->toIso8601String(),
            ],
        );

        event(new TaskCompleted($task->fresh()));

        $this->recalculateIssueStatus->handle($issue);

        return $task->fresh();
    }

    private function recordRequiredEsgMeasurement(
        Task $task,
        ?RecordEsgMeasurementData $esgMeasurement,
        ?Worker $worker,
    ): void {
        $issue = $task->issue;
        if ($issue === null || $issue->esg_indicator_id === null) {
            return;
        }

        if ($esgMeasurement === null) {
            throw ValidationException::withMessages([
                'esg_indicator_id' => [__('esg.errors.measurement_value_required')],
            ]);
        }

        if ($esgMeasurement->taskId !== (int) $task->id) {
            throw ValidationException::withMessages([
                'task_id' => [__('esg.errors.measurement_task_invalid')],
            ]);
        }

        app(RecordEsgMeasurementAction::class)->handle(
            $esgMeasurement,
            (int) $task->tenant_id,
            $worker?->id,
        );
    }
}
