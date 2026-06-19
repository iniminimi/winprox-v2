<?php

namespace App\Actions\Communication;

use App\Models\Task;
use App\Models\TaskTranslation;

class BackfillTaskTranslationSlotsAction
{
    public function __construct(private EnsureTaskTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{tasks: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $tasksProcessed = 0;
        $slotsCreated = 0;

        Task::query()
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use (&$tasksProcessed, &$slotsCreated): void {
                foreach ($tasks as $task) {
                    $before = TaskTranslation::query()
                        ->where('task_id', $task->id)
                        ->count();

                    $this->ensureSlots->handle($task);

                    $after = TaskTranslation::query()
                        ->where('task_id', $task->id)
                        ->count();

                    $tasksProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'tasks' => $tasksProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
