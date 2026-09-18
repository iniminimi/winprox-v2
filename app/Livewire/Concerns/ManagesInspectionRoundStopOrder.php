<?php

namespace App\Livewire\Concerns;

use App\Actions\Issues\CoalesceInspectionRoundStopsByLocationAction;
use App\Actions\Issues\MergeInspectionRoundStopSelectionAction;
use App\Actions\Issues\ReorderInspectionRoundLocationAction;
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
            $this->applyRoundStopOrder($ids);

            return;
        }

        unset($ids[$position]);
        $this->applyRoundStopOrder(array_values($ids));
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

        $this->applyRoundStopOrder(app(MergeInspectionRoundStopSelectionAction::class)->handle(
            $selected,
            $allIds,
            $this->roundStopLocationByUnitId(),
        ));
    }

    public function reorderRoundStop(int $from, int $to): void
    {
        $this->applyRoundStopOrder(app(ReorderInspectionRoundStopAction::class)->handle(
            $this->normalizedRoundStopUnitIds(),
            $from,
            $to,
            $this->roundStopLocationByUnitId(),
        ));
    }

    public function reorderRoundLocation(int $fromLocationId, int $toLocationId): void
    {
        $this->applyRoundStopOrder(app(ReorderInspectionRoundLocationAction::class)->handle(
            $this->normalizedRoundStopUnitIds(),
            $fromLocationId,
            $toLocationId,
            $this->roundStopLocationByUnitId(),
        ));
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

    /**
     * @param  list<int|string>  $ids
     */
    protected function applyRoundStopOrder(array $ids): void
    {
        $this->round_stop_unit_ids = app(CoalesceInspectionRoundStopsByLocationAction::class)->handle(
            $ids,
            $this->roundStopLocationByUnitId(),
        );
    }

    /**
     * @return array<int, int>
     */
    protected function roundStopLocationByUnitId(): array
    {
        [$grouped] = Unit::groupedInspectionRoundStops();
        $map = [];
        foreach ($grouped->flatten(1) as $unit) {
            $map[(int) $unit->id] = (int) ($unit->location_id ?? 0);
        }

        return $map;
    }
}
