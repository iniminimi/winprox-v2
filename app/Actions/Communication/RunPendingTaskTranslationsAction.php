<?php

namespace App\Actions\Communication;

use App\Enums\TaskTranslationStatus;
use App\Models\TaskTranslation;

class RunPendingTaskTranslationsAction
{
    public function __construct(private TranslateTaskAction $translateTask) {}

    public function handle(?int $limit = null, ?int $actorUserId = null): int
    {
        $limit = $limit ?? (int) config('ollama.batch_limit', 25);

        $rows = TaskTranslation::query()
            ->where('status', TaskTranslationStatus::Pending)
            ->whereHas('task', fn ($query) => $query->whereNotNull('description')->where('description', '!=', ''))
            ->with('task')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($rows as $row) {
            if ($row->task === null) {
                continue;
            }

            $this->translateTask->handle($row->task, $row->locale, $actorUserId);
            $processed++;
        }

        return $processed;
    }
}
