<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Verplaatst een stop van index $from naar index $to, alleen binnen dezelfde locatie.
 *
 * @param  list<int|string>  $ordered
 * @param  array<int, int>  $locationByUnitId
 * @return list<int>
 */
class ReorderInspectionRoundStopAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  array<int, int>  $locationByUnitId
     * @return list<int>
     */
    public function handle(array $ordered, int $from, int $to, array $locationByUnitId = []): array
    {
        $ids = [];
        foreach ($ordered as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        if ($locationByUnitId !== []) {
            $ids = app(CoalesceInspectionRoundStopsByLocationAction::class)->handle($ids, $locationByUnitId);
        }

        $count = count($ids);
        if ($from < 0 || $to < 0 || $from >= $count || $to >= $count || $from === $to) {
            return $ids;
        }

        if ($locationByUnitId !== []) {
            $fromLocation = (int) ($locationByUnitId[$ids[$from]] ?? 0);
            $toLocation = (int) ($locationByUnitId[$ids[$to]] ?? 0);
            if ($fromLocation !== $toLocation) {
                return $ids;
            }
        }

        $moved = array_splice($ids, $from, 1);
        array_splice($ids, $to, 0, $moved);

        return $ids;
    }
}
