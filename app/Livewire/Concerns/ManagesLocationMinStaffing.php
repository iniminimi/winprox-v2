<?php

namespace App\Livewire\Concerns;

use App\Enums\ShiftTypeKind;
use App\Models\Location;
use App\Models\ShiftType;

/**
 * Gedeelde logica voor het "Minimum bezetting per dag"-paneel op de
 * locatieformulieren (Locations\Index én Locations\Show).
 *
 * Component-contract: `public array $locationFormMinStaffing = []`.
 */
trait ManagesLocationMinStaffing
{
    public function addMinStaffingRow(): void
    {
        $this->locationFormMinStaffing[] = ['type_id' => '', 'min' => '1', 'stale_label' => null];
    }

    public function removeMinStaffingRow(int $index): void
    {
        unset($this->locationFormMinStaffing[$index]);
        $this->locationFormMinStaffing = array_values($this->locationFormMinStaffing);
    }

    /**
     * Min_staffing-rijen → {shift_type_id: min}. Lege/dubbele rijen vallen weg
     * (eerste wint); verouderde ids blijven bewust bewaard zolang de rij er staat.
     *
     * @return array<int, int>
     */
    private function normalizedMinStaffingRows(): array
    {
        $map = [];
        foreach ($this->locationFormMinStaffing as $row) {
            $typeId = (int) ($row['type_id'] ?? 0);
            $min = (int) ($row['min'] ?? 0);
            if ($typeId > 0 && $min > 0 && ! array_key_exists($typeId, $map)) {
                $map[$typeId] = min($min, 99);
            }
        }

        return $map;
    }

    private function hydrateMinStaffingRows(Location $location): void
    {
        $stored = (array) ($location->min_staffing ?? []);
        $storedIds = array_map('intval', array_keys($stored));

        // Alle types (ook inactief/afwezig-kind) zodat verouderde regels
        // herkenbaar getoond kunnen worden i.p.v. ongemerkt weg te vallen.
        $known = ShiftType::query()
            ->where('tenant_id', (int) $location->tenant_id)
            ->whereIn('id', $storedIds)
            ->get(['id', 'code', 'is_active', 'kind'])
            ->keyBy('id');

        $this->locationFormMinStaffing = [];
        foreach ($stored as $typeId => $min) {
            $typeId = (int) $typeId;
            $type = $known->get($typeId);
            $usable = $type !== null && $type->is_active && $type->kind?->isWork();

            $this->locationFormMinStaffing[] = [
                'type_id' => (string) $typeId,
                'min' => (string) (int) $min,
                'stale_label' => $usable ? null : ($type?->code ?? '?'),
            ];
        }
    }

    /**
     * Ids die de beheerder mag opslaan: actieve werk-types ∪ ids die al in de
     * opgeslagen config staan (verouderde regels blijven bewaard tot verwijderd).
     */
    private function minStaffingTypeIdsValid(Location $location): bool
    {
        $requested = array_keys($this->normalizedMinStaffingRows());
        if ($requested === []) {
            return true;
        }

        $allowed = ShiftType::query()
            ->where('tenant_id', (int) $location->tenant_id)
            ->where('is_active', true)
            ->where('kind', ShiftTypeKind::Work->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge(array_map('intval', array_keys((array) ($location->min_staffing ?? []))))
            ->all();

        return count(array_diff($requested, $allowed)) === 0;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function minStaffingTypeOptions(int $tenantId): array
    {
        return ShiftType::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('kind', ShiftTypeKind::Work->value)
            ->orderBy('code')
            ->get(['id', 'code', 'label'])
            ->map(fn (ShiftType $type): array => [
                'id' => (int) $type->id,
                'label' => trim($type->code.' — '.$type->label, ' —'),
            ])
            ->all();
    }
}
