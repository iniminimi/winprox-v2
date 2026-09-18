<?php

declare(strict_types=1);

namespace App\Actions\Issues;

/**
 * Groepeert stops per locatie in de volgorde waarin locaties voor het eerst voorkomen.
 *
 * @param  list<int|string>  $ordered
 * @param  array<int, int>  $locationByUnitId
 * @return list<array{location_id: int, unit_ids: list<int>}>
 */
class GroupInspectionRoundStopsByLocationAction
{
    /**
     * @param  list<int|string>  $ordered
     * @param  array<int, int>  $locationByUnitId
     * @return list<array{location_id: int, unit_ids: list<int>}>
     */
    public function handle(array $ordered, array $locationByUnitId): array
    {
        $locationOrder = [];
        $unitIdsByLocation = [];
        foreach ($ordered as $id) {
            if (! is_numeric($id)) {
                continue;
            }
            $intId = (int) $id;
            $locationId = (int) ($locationByUnitId[$intId] ?? 0);
            if (! array_key_exists($locationId, $unitIdsByLocation)) {
                $locationOrder[] = $locationId;
                $unitIdsByLocation[$locationId] = [];
            }
            $unitIdsByLocation[$locationId][] = $intId;
        }

        $blocks = [];
        foreach ($locationOrder as $locationId) {
            $blocks[] = [
                'location_id' => $locationId,
                'unit_ids' => $unitIdsByLocation[$locationId],
            ];
        }

        return $blocks;
    }
}
