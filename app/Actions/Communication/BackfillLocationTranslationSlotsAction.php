<?php

namespace App\Actions\Communication;

use App\Models\Location;
use App\Models\LocationTranslation;

class BackfillLocationTranslationSlotsAction
{
    public function __construct(private EnsureLocationTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{locations: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $locationsProcessed = 0;
        $slotsCreated = 0;

        Location::query()
            ->where('is_active', true)
            ->where('name', '!=', '')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($locations) use (&$locationsProcessed, &$slotsCreated): void {
                foreach ($locations as $location) {
                    $before = LocationTranslation::query()
                        ->where('location_id', $location->id)
                        ->count();

                    $this->ensureSlots->handle($location);

                    $after = LocationTranslation::query()
                        ->where('location_id', $location->id)
                        ->count();

                    $locationsProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'locations' => $locationsProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
