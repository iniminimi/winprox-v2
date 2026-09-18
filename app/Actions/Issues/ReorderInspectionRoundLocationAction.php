<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Verplaatst een heel locatie-blok in de ronde (units blijven bij elkaar).
 *
 * @param  list<int|string>  $ordered
 * @param  array<int, int>  $locationByUnitId
 * @return list<int>
 */
class ReorderInspectionRoundLocationAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  array<int, int>  $locationByUnitId
     * @return list<int>
     */
    public function handle(array $ordered, int $fromLocationId, int $toLocationId, array $locationByUnitId): array
    {
        $blocks = app(GroupInspectionRoundStopsByLocationAction::class)->handle($ordered, $locationByUnitId);
        $from = null;
        $to = null;
        foreach ($blocks as $index => $block) {
            if ($block['location_id'] === $fromLocationId) {
                $from = $index;
            }
            if ($block['location_id'] === $toLocationId) {
                $to = $index;
            }
        }

        if ($from === null || $to === null || $from === $to) {
            return app(CoalesceInspectionRoundStopsByLocationAction::class)->handle($ordered, $locationByUnitId);
        }

        $moved = array_splice($blocks, $from, 1);
        array_splice($blocks, $to, 0, $moved);

        $ids = [];
        foreach ($blocks as $block) {
            foreach ($block['unit_ids'] as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
