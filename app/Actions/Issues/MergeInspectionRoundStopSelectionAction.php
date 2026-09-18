<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Behoudt de bestaande stop-volgorde en zet nieuw geselecteerde units achteraan.
 * Met locatie-kaart blijven units van dezelfde locatie bij elkaar.
 *
 * @param  list<int>  $ordered
 * @param  list<int>  $selected
 * @param  array<int, int>  $locationByUnitId
 * @return list<int>
 */
class MergeInspectionRoundStopSelectionAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  list<int|string>  $selected
     * @param  array<int, int>  $locationByUnitId
     * @return list<int>
     */
    public function handle(array $ordered, array $selected, array $locationByUnitId = []): array
    {
        $selectedIds = [];
        foreach ($selected as $id) {
            if (! is_numeric($id)) {
                continue;
            }
            $intId = (int) $id;
            if (! in_array($intId, $selectedIds, true)) {
                $selectedIds[] = $intId;
            }
        }

        $selectedSet = array_fill_keys($selectedIds, true);
        $kept = [];
        foreach ($ordered as $id) {
            if (! is_numeric($id)) {
                continue;
            }
            $intId = (int) $id;
            if (isset($selectedSet[$intId])) {
                $kept[] = $intId;
                unset($selectedSet[$intId]);
            }
        }

        foreach ($selectedIds as $intId) {
            if (isset($selectedSet[$intId])) {
                $kept[] = $intId;
                unset($selectedSet[$intId]);
            }
        }

        if ($locationByUnitId === []) {
            return $kept;
        }

        return app(CoalesceInspectionRoundStopsByLocationAction::class)->handle($kept, $locationByUnitId);
    }
}
