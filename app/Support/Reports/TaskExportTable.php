<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\Task;
use Illuminate\Support\Collection;

final class TaskExportTable
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            __('reports.columns.id'),
            __('reports.columns.status'),
            __('reports.columns.priority'),
            __('reports.columns.description'),
            __('reports.columns.issue_id'),
            __('reports.columns.location'),
            __('reports.columns.unit'),
            __('reports.columns.team'),
            __('reports.columns.created_at'),
            __('reports.columns.scheduled_for'),
            __('reports.columns.due_at'),
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, list<string>>
     */
    public static function rows(Collection $tasks): Collection
    {
        return $tasks->map(function (Task $task): array {
            return [
                (string) $task->id,
                __($task->status->labelKey()),
                $task->priority?->label() ?? '',
                $task->displayDescription(),
                (string) $task->issue_id,
                (string) ($task->issue?->location?->name ?? ''),
                (string) ($task->issue?->unit?->localizedName() ?? $task->issue?->unit?->name ?? ''),
                (string) ($task->team?->localizedName() ?? ''),
                $task->created_at?->format('Y-m-d H:i') ?? '',
                $task->scheduled_for?->format('Y-m-d') ?? '',
                $task->due_at?->format('Y-m-d') ?? '',
            ];
        });
    }
}
