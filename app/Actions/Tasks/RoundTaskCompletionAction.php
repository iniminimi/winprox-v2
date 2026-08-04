<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Enums\UnitCheckResult;
use App\Models\Task;
use App\Models\UnitCheck;
use Illuminate\Support\Collection;

/**
 * Completion-query (4 / 4b): open = stops(issue) − (OK ∪ skipped voor deze task_id).
 */
class RoundTaskCompletionAction
{
    /**
     * @return Collection<int, int> Open stop unit_ids (sort_order behouden).
     */
    public function openStopUnitIds(Task $task): Collection
    {
        $task->loadMissing('issue.roundStops');

        $stops = $task->issue?->roundStops ?? collect();
        if ($stops->isEmpty()) {
            return collect();
        }

        $okUnitIds = UnitCheck::query()
            ->where('task_id', $task->id)
            ->where('result', UnitCheckResult::Ok->value)
            ->whereIn('unit_id', $stops->pluck('unit_id'))
            ->pluck('unit_id')
            ->unique()
            ->all();

        $skippedUnitIds = $task->roundStopSkips()
            ->whereIn('unit_id', $stops->pluck('unit_id'))
            ->pluck('unit_id')
            ->unique()
            ->all();

        $done = array_unique([...$okUnitIds, ...$skippedUnitIds]);

        return $stops
            ->sortBy('sort_order')
            ->pluck('unit_id')
            ->reject(fn (int $unitId) => in_array($unitId, $done, true))
            ->values();
    }

    public function isComplete(Task $task): bool
    {
        $task->loadMissing('issue.roundStops');

        if ($task->issue === null || $task->issue->roundStops->isEmpty()) {
            return false;
        }

        return $this->openStopUnitIds($task)->isEmpty();
    }

    /**
     * @return array{total: int, ok: int, skipped: int, open: int}
     */
    public function progress(Task $task): array
    {
        $task->loadMissing(['issue.roundStops', 'roundStopSkips']);

        $total = $task->issue?->roundStops->count() ?? 0;
        $open = $this->openStopUnitIds($task)->count();
        $skipped = $task->roundStopSkips()
            ->whereIn('unit_id', $task->issue?->roundStops->pluck('unit_id') ?? [])
            ->count();
        $ok = max(0, $total - $open - $skipped);

        return [
            'total' => $total,
            'ok' => $ok,
            'skipped' => $skipped,
            'open' => $open,
        ];
    }
}
