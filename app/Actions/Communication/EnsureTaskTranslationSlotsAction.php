<?php

namespace App\Actions\Communication;

use App\Models\Tenant;
use App\Enums\TaskTranslationStatus;
use App\Models\Task;
use App\Models\TaskTranslation;
use App\Support\Translation\LocaleSupport;

class EnsureTaskTranslationSlotsAction
{
    public function handle(Task $task): void
    {
        if (! Tenant::translationsEnabledFor((int) $task->tenant_id)) {
            return;
        }

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
