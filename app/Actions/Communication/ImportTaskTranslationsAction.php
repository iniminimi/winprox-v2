<?php

namespace App\Actions\Communication;

use App\Enums\TaskTranslationStatus;
use App\Events\Tasks\TaskTranslationImported;
use App\Models\Task;
use App\Models\TaskTranslation;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class ImportTaskTranslationsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureTaskTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $items, ?int $actorUserId = null): int
    {
        $imported = 0;

        foreach ($items as $index => $item) {
            $taskId = (int) ($item['task_id'] ?? 0);
            $locale = LocaleSupport::normalize((string) ($item['locale'] ?? ''));
            $description = trim((string) ($item['description'] ?? $item['text'] ?? ''));

            if ($taskId <= 0 || $locale === '' || $description === '') {
                throw ValidationException::withMessages([
                    "items.{$index}" => [__('issues.errors.translation_import_invalid')],
                ]);
            }

            if (mb_strlen($description) > TextDescriptionLimits::TRANSLATION_MAX) {
                throw ValidationException::withMessages([
                    "items.{$index}.description" => [__('issues.errors.translation_import_too_long')],
                ]);
            }

            $task = Task::query()->find($taskId);

            if ($task === null || ! filled(trim((string) ($task->description ?? '')))) {
                throw ValidationException::withMessages([
                    "items.{$index}.task_id" => [__('tasks.errors.translation_import_missing')],
                ]);
            }

            if ($locale === $task->normalizedOriginalLanguage()) {
                continue;
            }

            $this->ensureSlots->handle($task);

            $row = TaskTranslation::query()
                ->where('task_id', $task->id)
                ->where('locale', $locale)
                ->firstOrFail();

            $row->fill([
                'description' => $description,
                'status' => TaskTranslationStatus::Completed,
            ])->save();

            $this->audit->record(
                $actorUserId,
                (int) $task->tenant_id,
                'task.translation_imported',
                TaskTranslation::class,
                (int) $row->id,
                [
                    'task_id' => $task->id,
                    'locale' => $locale,
                ],
            );

            TaskTranslationImported::dispatch($row, $actorUserId);

            $imported++;
        }

        return $imported;
    }
}
