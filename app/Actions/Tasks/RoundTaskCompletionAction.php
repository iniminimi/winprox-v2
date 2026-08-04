<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Enums\UnitCheckResult;
use App\Models\IssueRoundStop;
use App\Models\Task;
use App\Models\UnitCheck;
use Illuminate\Support\Collection;

/**
 * Completion-query (4 / 4b): open = stops(issue) − (OK ∪ skipped voor deze task_id).
 * Fase 2: strikte volgorde — alleen de eerste open stop (sort_order) mag OK/skip.
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

        $done = $this->doneUnitIds($task, $stops->pluck('unit_id')->all());

        return $stops
            ->sortBy('sort_order')
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $unitId) => in_array($unitId, $done, true))
            ->values();
    }

    public function nextOpenStopUnitId(Task $task): ?int
    {
        $open = $this->openStopUnitIds($task);

        return $open->isEmpty() ? null : (int) $open->first();
    }

    public function isNextOpenStop(Task $task, int $unitId): bool
    {
        $next = $this->nextOpenStopUnitId($task);

        return $next !== null && $next === $unitId;
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
     * @return array{
     *     total: int,
     *     ok: int,
     *     skipped: int,
     *     open: int,
     *     done: int,
     *     next_unit_id: int|null,
     *     next_unit_name: string|null,
     *     stops: list<array{unit_id: int, name: string, state: string, sort_order: int}>
     * }
     */
    public function progress(Task $task): array
    {
        $task->loadMissing(['issue.roundStops.unit.translations', 'roundStopSkips']);

        $stops = ($task->issue?->roundStops ?? collect())->sortBy('sort_order')->values();
        $total = $stops->count();
        $openIds = $this->openStopUnitIds($task);
        $nextId = $openIds->isEmpty() ? null : (int) $openIds->first();
        $skippedIds = $task->roundStopSkips
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();
        $okIds = UnitCheck::query()
            ->where('task_id', $task->id)
            ->where('result', UnitCheckResult::Ok->value)
            ->whereIn('unit_id', $stops->pluck('unit_id'))
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $stopRows = [];
        foreach ($stops as $stop) {
            /** @var IssueRoundStop $stop */
            $unitId = (int) $stop->unit_id;
            $state = match (true) {
                in_array($unitId, $okIds, true) => 'ok',
                in_array($unitId, $skippedIds, true) => 'skipped',
                $nextId !== null && $unitId === $nextId => 'current',
                default => 'open',
            };
            $stopRows[] = [
                'unit_id' => $unitId,
                'name' => $stop->unit?->localizedName() ?? ('#'.$unitId),
                'state' => $state,
                'sort_order' => (int) $stop->sort_order,
            ];
        }

        $open = $openIds->count();
        $skipped = count(array_intersect($skippedIds, $stops->pluck('unit_id')->map(fn ($id) => (int) $id)->all()));
        $ok = count(array_intersect($okIds, $stops->pluck('unit_id')->map(fn ($id) => (int) $id)->all()));
        $nextName = null;
        if ($nextId !== null) {
            $nextName = collect($stopRows)->firstWhere('unit_id', $nextId)['name'] ?? null;
        }

        return [
            'total' => $total,
            'ok' => $ok,
            'skipped' => $skipped,
            'open' => $open,
            'done' => max(0, $total - $open),
            'next_unit_id' => $nextId,
            'next_unit_name' => $nextName,
            'stops' => $stopRows,
        ];
    }

    /**
     * @param  list<int|string>  $stopUnitIds
     * @return list<int>
     */
    private function doneUnitIds(Task $task, array $stopUnitIds): array
    {
        $okUnitIds = UnitCheck::query()
            ->where('task_id', $task->id)
            ->where('result', UnitCheckResult::Ok->value)
            ->whereIn('unit_id', $stopUnitIds)
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $skippedUnitIds = $task->roundStopSkips()
            ->whereIn('unit_id', $stopUnitIds)
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return array_values(array_unique([...$okUnitIds, ...$skippedUnitIds]));
    }
}
