<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Wisselt twee opeenvolgende stops (delta -1 = eerder, +1 = later).
 * Met locatie-kaart: geen wissel over een locatiegrens.
 *
 * @param  list<int|string>  $ordered
 * @param  array<int, int>  $locationByUnitId
 * @return list<int>
 */
class MoveInspectionRoundStopAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  array<int, int>  $locationByUnitId
     * @return list<int>
     */
    public function handle(array $ordered, int $index, int $delta, array $locationByUnitId = []): array
    {
        return app(ReorderInspectionRoundStopAction::class)->handle(
            $ordered,
            $index,
            $index + $delta,
            $locationByUnitId,
        );
    }
}
