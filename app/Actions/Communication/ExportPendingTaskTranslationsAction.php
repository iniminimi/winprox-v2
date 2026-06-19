<?php

namespace App\Actions\Communication;

use App\Enums\TaskTranslationStatus;
use App\Models\TaskTranslation;

class ExportPendingTaskTranslationsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        return TaskTranslation::query()
            ->where('status', TaskTranslationStatus::Pending)
            ->whereHas('task', fn ($query) => $query->whereNotNull('description')->where('description', '!=', ''))
            ->with('task')
            ->orderBy('task_id')
            ->orderBy('locale')
            ->get()
            ->map(function (TaskTranslation $row): array {
                $task = $row->task;

                return [
                    'task_id' => $task->id,
                    'tenant_id' => $task->tenant_id,
                    'source_locale' => $task->normalizedOriginalLanguage(),
                    'source_text' => (string) $task->description,
                    'locale' => $row->locale,
                    'status' => TaskTranslationStatus::Pending->value,
                ];
            })
            ->values()
            ->all();
    }
}
