<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Enums\UnitCheckResult;
use App\Models\IssueRoundStop;
use App\Models\Task;
use App\Models\UnitCheck;
use Illuminate\Support\Collection;

/**
 * Completion-query (4 / 4b): open = stops(issue) − (OK ∪ Niet OK ∪ skipped voor deze task_id).
 * Fase 2: strikte volgorde — alleen de eerste open stop (sort_order) mag OK/Niet OK/skip.
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
     *     not_ok: int,
     *     skipped: int,
     *     open: int,
     *     done: int,
     *     next_unit_id: int|null,
     *     next_unit_name: string|null,
     *     stops: list<array{
     *         unit_id: int,
     *         name: string,
     *         state: string,
     *         sort_order: int,
     *         at: string|null,
     *         worker_name: string|null
     *     }>
     * }
     */
    public function progress(Task $task): array
    {
        $task->loadMissing([
            'issue.roundStops.unit.translations',
            'issue.roundStops.unit.location',
            'roundStopSkips.worker',
        ]);

        $stops = ($task->issue?->roundStops ?? collect())->sortBy('sort_order')->values();
        $stopUnitIds = $stops->pluck('unit_id')->map(fn ($id) => (int) $id)->all();
        $total = $stops->count();
        $openIds = $this->openStopUnitIds($task);
        $nextId = $openIds->isEmpty() ? null : (int) $openIds->first();
        $multiLocation = $stops
            ->map(fn (IssueRoundStop $stop) => $stop->unit?->location_id)
            ->filter()
            ->unique()
            ->count() > 1;

        $skippedByUnit = $task->roundStopSkips
            ->keyBy(fn ($skip) => (int) $skip->unit_id);

        $checksByUnit = UnitCheck::query()
            ->where('task_id', $task->id)
            ->whereIn('unit_id', $stopUnitIds === [] ? [0] : $stopUnitIds)
            ->whereIn('result', [UnitCheckResult::Ok->value, UnitCheckResult::NotOk->value])
            ->with('worker')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (UnitCheck $check) => (int) $check->unit_id)
            ->keyBy(fn (UnitCheck $check) => (int) $check->unit_id);

        $okIds = $checksByUnit
            ->filter(fn (UnitCheck $check) => $check->result === UnitCheckResult::Ok)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
        $notOkIds = $checksByUnit
            ->filter(fn (UnitCheck $check) => $check->result === UnitCheckResult::NotOk)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
        $skippedIds = $skippedByUnit->keys()->map(fn ($id) => (int) $id)->all();

        $stopRows = [];
        foreach ($stops as $stop) {
            /** @var IssueRoundStop $stop */
            $unitId = (int) $stop->unit_id;
            $state = match (true) {
                in_array($unitId, $okIds, true) => 'ok',
                in_array($unitId, $notOkIds, true) => 'not_ok',
                in_array($unitId, $skippedIds, true) => 'skipped',
                $nextId !== null && $unitId === $nextId => 'current',
                default => 'open',
            };

            $at = null;
            $workerName = null;
            if ($state === 'ok' || $state === 'not_ok') {
                $check = $checksByUnit->get($unitId);
                $at = $check?->checked_at?->format('d/m/Y H:i');
                $workerName = $check?->worker?->displayName();
            } elseif ($state === 'skipped') {
                $skip = $skippedByUnit->get($unitId);
                $at = $skip?->created_at?->format('d/m/Y H:i');
                $workerName = $skip?->worker?->displayName();
            }

            $unitName = $stop->unit?->localizedName() ?? ('#'.$unitId);
            $locationName = $stop->unit?->location?->name
                ?: ($stop->unit?->location?->address ?? null);
            $stopName = $multiLocation && filled($locationName)
                ? $locationName.' · '.$unitName
                : $unitName;

            $stopRows[] = [
                'unit_id' => $unitId,
                'name' => $stopName,
                'state' => $state,
                'sort_order' => (int) $stop->sort_order,
                'at' => $at,
                'worker_name' => $workerName,
            ];
        }

        $open = $openIds->count();
        $skipped = count(array_intersect($skippedIds, $stopUnitIds));
        $ok = count(array_intersect($okIds, $stopUnitIds));
        $notOk = count(array_intersect($notOkIds, $stopUnitIds));
        $nextName = null;
        if ($nextId !== null) {
            $nextName = collect($stopRows)->firstWhere('unit_id', $nextId)['name'] ?? null;
        }

        return [
            'total' => $total,
            'ok' => $ok,
            'not_ok' => $notOk,
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

        $notOkUnitIds = UnitCheck::query()
            ->where('task_id', $task->id)
            ->where('result', UnitCheckResult::NotOk->value)
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

        return array_values(array_unique([...$okUnitIds, ...$notOkUnitIds, ...$skippedUnitIds]));
    }
}
