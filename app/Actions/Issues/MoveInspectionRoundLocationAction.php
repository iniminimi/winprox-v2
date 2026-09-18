<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Wisselt twee opeenvolgende locatie-blokken (delta -1 = eerder, +1 = later).
 *
 * @param  list<int|string>  $ordered
 * @param  array<int, int>  $locationByUnitId
 * @return list<int>
 */
class MoveInspectionRoundLocationAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  array<int, int>  $locationByUnitId
     * @return list<int>
     */
    public function handle(array $ordered, int $locationId, int $delta, array $locationByUnitId): array
    {
        $blocks = app(GroupInspectionRoundStopsByLocationAction::class)->handle($ordered, $locationByUnitId);
        $index = null;
        foreach ($blocks as $i => $block) {
            if ($block['location_id'] === $locationId) {
                $index = $i;
                break;
            }
        }

        $target = $index === null ? null : ($blocks[$index + $delta]['location_id'] ?? null);
        if ($index === null || $target === null) {
            return app(CoalesceInspectionRoundStopsByLocationAction::class)->handle($ordered, $locationByUnitId);
        }

        return app(ReorderInspectionRoundLocationAction::class)->handle(
            $ordered,
            $locationId,
            (int) $target,
            $locationByUnitId,
        );
    }
}
