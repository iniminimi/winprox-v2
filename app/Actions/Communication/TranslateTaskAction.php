<?php

namespace App\Actions\Communication;

use App\Enums\TaskTranslationStatus;
use App\Models\Task;
use App\Models\TaskTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Translation\TranslationOutputGuard;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;

class TranslateTaskAction
{
    public function __construct(
        private TranslationProviderInterface $translator,
        private AuditRecorder $audit,
        private EnsureTaskTranslationSlotsAction $ensureSlots,
    ) {}

    public function handle(Task $task, string $targetLocale, ?int $actorUserId = null): TaskTranslation
    {
        $sourceText = trim((string) ($task->description ?? ''));

        if ($sourceText === '') {
            throw ValidationException::withMessages([
                'task' => [__('tasks.errors.translation_requires_description')],
            ]);
        }

        $targetLocale = LocaleSupport::normalize($targetLocale);

        if ($targetLocale === $task->normalizedOriginalLanguage()) {
            throw ValidationException::withMessages([
                'locale' => [__('issues.errors.translation_same_as_source')],
            ]);
        }

        $this->ensureSlots->handle($task);

        $row = TaskTranslation::query()
            ->where('task_id', $task->id)
            ->where('locale', $targetLocale)
            ->firstOrFail();

        if ($row->status === TaskTranslationStatus::Completed && filled($row->description)) {
            return $row;
        }

        $translated = trim($this->translator->translate($sourceText, $targetLocale));

        if (
            $translated === ''
            || TranslationOutputGuard::isUnusable($translated, $sourceText)
        ) {
            return $this->storeFailed($row, $task, $targetLocale, $actorUserId, 'translation_empty_or_unusable');
        }

        if (mb_strlen($translated) > TextDescriptionLimits::TRANSLATION_MAX) {
            return $this->storeFailed($row, $task, $targetLocale, $actorUserId, 'translation_too_long');
        }

        $row->fill([
            'description' => $translated,
            'status' => TaskTranslationStatus::Completed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $task->tenant_id,
            'task.translation_stored',
            TaskTranslation::class,
            (int) $row->id,
            [
                'task_id' => $task->id,
                'locale' => $targetLocale,
                'status' => TaskTranslationStatus::Completed->value,
            ],
        );

        return $row->fresh();
    }

    private function storeFailed(
        TaskTranslation $row,
        Task $task,
        string $targetLocale,
        ?int $actorUserId,
        string $reason,
    ): TaskTranslation {
        $row->fill([
            'description' => null,
            'status' => TaskTranslationStatus::Failed,
        ])->save();

        $this->audit->record(
            $actorUserId,
            (int) $task->tenant_id,
            'task.translation_stored',
            TaskTranslation::class,
            (int) $row->id,
            [
                'task_id' => $task->id,
                'locale' => $targetLocale,
                'status' => TaskTranslationStatus::Failed->value,
                'reason' => $reason,
            ],
        );

        return $row->fresh();
    }
}
