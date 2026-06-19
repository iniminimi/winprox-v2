<?php

namespace App\Actions\Communication;

use App\Models\Unit;
use App\Models\UnitTranslation;

class BackfillUnitTranslationSlotsAction
{
    public function __construct(private EnsureUnitTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{units: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $unitsProcessed = 0;
        $slotsCreated = 0;

        Unit::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($units) use (&$unitsProcessed, &$slotsCreated): void {
                foreach ($units as $unit) {
                    $before = UnitTranslation::query()
                        ->where('unit_id', $unit->id)
                        ->count();

                    $this->ensureSlots->handle($unit);

                    $after = UnitTranslation::query()
                        ->where('unit_id', $unit->id)
                        ->count();

                    $unitsProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'units' => $unitsProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
