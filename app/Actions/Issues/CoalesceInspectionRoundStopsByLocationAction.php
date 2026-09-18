<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Houdt locatie-blokken aaneengesloten (geen heen-en-weer tussen locaties).
 *
 * @param  list<int|string>  $ordered
 * @param  array<int, int>  $locationByUnitId
 * @return list<int>
 */
class CoalesceInspectionRoundStopsByLocationAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  array<int, int>  $locationByUnitId
     * @return list<int>
     */
    public function handle(array $ordered, array $locationByUnitId): array
    {
        $ids = [];
        foreach (app(GroupInspectionRoundStopsByLocationAction::class)->handle($ordered, $locationByUnitId) as $block) {
            foreach ($block['unit_ids'] as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
