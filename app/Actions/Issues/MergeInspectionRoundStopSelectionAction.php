<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Behoudt de bestaande stop-volgorde en zet nieuw geselecteerde units achteraan.
 *
 * @param  list<int>  $ordered
 * @param  list<int>  $selected
 * @return list<int>
 */
class MergeInspectionRoundStopSelectionAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  list<int|string>  $selected
     * @return list<int>
     */
    public function handle(array $ordered, array $selected): array
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

        return $kept;
    }
}
