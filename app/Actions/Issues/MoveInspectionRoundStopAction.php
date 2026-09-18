<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Wisselt twee opeenvolgende stops in de geordende lijst (delta -1 = eerder, +1 = later).
 *
 * @param  list<int|string>  $ordered
 * @return list<int>
 */
class MoveInspectionRoundStopAction
{
    /**
     * @param  list<int|string>  $ordered
     * @return list<int>
     */
    public function handle(array $ordered, int $index, int $delta): array
    {
        return app(ReorderInspectionRoundStopAction::class)->handle($ordered, $index, $index + $delta);
    }
}
