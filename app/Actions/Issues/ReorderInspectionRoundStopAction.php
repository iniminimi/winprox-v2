<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Verplaatst een stop van index $from naar index $to (overige stops schuiven).
 *
 * @param  list<int|string>  $ordered
 * @return list<int>
 */
class ReorderInspectionRoundStopAction
{
    /**
     * @param  list<int|string>  $ordered
     * @return list<int>
     */
    public function handle(array $ordered, int $from, int $to): array
    {
        $ids = [];
        foreach ($ordered as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        $count = count($ids);
        if ($from < 0 || $to < 0 || $from >= $count || $to >= $count || $from === $to) {
            return $ids;
        }

        $moved = array_splice($ids, $from, 1);
        array_splice($ids, $to, 0, $moved);

        return $ids;
    }
}
