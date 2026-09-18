<?php

namespace App\Livewire\Concerns;

use App\Actions\Issues\MergeInspectionRoundStopSelectionAction;
use App\Actions\Issues\MoveInspectionRoundStopAction;
use App\Actions\Issues\ReorderInspectionRoundStopAction;
use App\Models\Unit;

trait ManagesInspectionRoundStopOrder
{
    /** @var list<int|string> */
    public array $round_stop_unit_ids = [];

    public function toggleRoundStop(int $unitId): void
    {
        $ids = $this->normalizedRoundStopUnitIds();
        $position = array_search($unitId, $ids, true);
        if ($position === false) {
            $ids[] = $unitId;
            $this->round_stop_unit_ids = $ids;

            return;
        }

        unset($ids[$position]);
        $this->round_stop_unit_ids = array_values($ids);
    }

    public function toggleAllRoundStops(): void
    {
        [$grouped] = Unit::groupedInspectionRoundStops();
        $allIds = $grouped->flatten(1)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $selected = $this->normalizedRoundStopUnitIds();
        if ($allIds !== [] && array_diff($allIds, $selected) === []) {
            $this->round_stop_unit_ids = [];

            return;
        }

        $this->round_stop_unit_ids = app(MergeInspectionRoundStopSelectionAction::class)->handle(
            $selected,
            $allIds,
        );
    }

    public function moveRoundStop(int $index, int $delta): void
    {
        $this->round_stop_unit_ids = app(MoveInspectionRoundStopAction::class)->handle(
            $this->normalizedRoundStopUnitIds(),
            $index,
            $delta,
        );
    }

    public function reorderRoundStop(int $from, int $to): void
    {
        $this->round_stop_unit_ids = app(ReorderInspectionRoundStopAction::class)->handle(
            $this->normalizedRoundStopUnitIds(),
            $from,
            $to,
        );
    }

    /**
     * @return list<int>
     */
    protected function normalizedRoundStopUnitIds(): array
    {
        return array_values(array_filter(array_map(
            static fn ($id) => is_numeric($id) ? (int) $id : null,
            $this->round_stop_unit_ids,
        )));
    }
}
