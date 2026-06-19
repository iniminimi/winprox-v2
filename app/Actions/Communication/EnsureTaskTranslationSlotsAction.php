<?php

namespace App\Actions\Communication;

use App\Enums\TaskTranslationStatus;
use App\Models\Task;
use App\Models\TaskTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureTaskTranslationSlotsAction
{
    public function handle(Task $task): void
    {
        if (! filled(trim((string) ($task->description ?? '')))) {
            return;
        }

        foreach (LocaleSupport::targetLocalesForSource($task->original_language) as $locale) {
            TaskTranslation::firstOrCreate(
                [
                    'task_id' => $task->id,
                    'locale' => $locale,
                ],
                [
                    'status' => TaskTranslationStatus::Pending,
                ],
            );
        }
    }
}
