<?php

namespace App\Actions\Communication;

use App\Enums\TaskTranslationStatus;
use App\Models\TaskTranslation;

class RunPendingTaskTranslationsAction
{
    public function __construct(private TranslateTaskAction $translateTask) {}

    public function handle(?int $limit = null, ?int $actorUserId = null, ?callable $onProgress = null): int
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

        $total = $rows->count();
        $onProgress?->__invoke($processed, $total, null, null);

        foreach ($rows as $row) {
            if ($row->task === null) {
                continue;
            }

            $this->translateTask->handle($row->task, $row->locale, $actorUserId);
            $processed++;
            $onProgress?->__invoke($processed, $total, $row->id, $row->locale);
        }

        return $processed;
    }
}
