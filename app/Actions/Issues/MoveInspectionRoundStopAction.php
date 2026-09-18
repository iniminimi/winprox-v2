<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Wisselt twee opeenvolgende stops in de geordende lijst (delta -1 = eerder, +1 = later).
 *
 * @param  list<int>  $ordered
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
        $ids = [];
        foreach ($ordered as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        $target = $index + $delta;
        if ($index < 0 || $index >= count($ids) || $target < 0 || $target >= count($ids)) {
            return $ids;
        }

        $swap = $ids[$index];
        $ids[$index] = $ids[$target];
        $ids[$target] = $swap;

        return $ids;
    }
}
