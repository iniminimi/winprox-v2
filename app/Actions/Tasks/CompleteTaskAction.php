<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCompleted;
use App\Models\IssueUpdate;
use App\Models\Task;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;

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
    ) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Task $task, ?Worker $worker = null, ?string $note = null, array $photos = [], ?\Carbon\Carbon $clientTimestamp = null): Task
    {
        if (! $task->canComplete()) {
            return $task;
        }

        $issue = $task->issue;
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        if ($note !== null && $worker !== null) {
            $issue->updates()->create([
                'worker_id' => $worker->id,
                'kind' => 'worker_note',
                'body' => $note,
            ]);
        }

        $files = array_values(array_filter($photos, fn ($photo) => $photo instanceof UploadedFile));
        if ($files !== [] && $worker !== null) {
            /** @var IssueUpdate $update */
            $update = $issue->updates()->create([
                'worker_id' => $worker->id,
                'kind' => 'worker_photos',
                'body' => null,
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

        $this->audit->record(
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

        $issue->recalculateStatus();

        return $task->fresh();
    }
}
