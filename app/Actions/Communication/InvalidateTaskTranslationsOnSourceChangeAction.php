<?php

namespace App\Actions\Communication;

use App\Enums\TaskTranslationStatus;
use App\Models\Task;
use App\Models\TaskTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateTaskTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Task $task, ?string $previousDescription, ?int $actorUserId = null): void
    {
        $descriptionChanged = trim((string) $previousDescription) !== trim((string) ($task->description ?? ''));

        if (! $descriptionChanged) {
            return;
        }

        if (! filled(trim((string) ($task->description ?? '')))) {
            return;
        }

        $source = $task->normalizedOriginalLanguage();

        $invalidated = TaskTranslation::query()
            ->where('task_id', $task->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', TaskTranslationStatus::Pending->value)
                    ->orWhereNotNull('description');
            })
            ->update([
                'description' => null,
                'status' => TaskTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $task->tenant_id,
            'task.translations_invalidated',
            Task::class,
            (int) $task->id,
            [
                'task_id' => $task->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
