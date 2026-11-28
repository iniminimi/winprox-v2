<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Sense\SenseReadingCatalog;
use App\Data\Sense\SenseReadingScreenChoice;
use App\Models\SensePoint;
use App\Support\Sense\SenseBands;

class ListSenseReadingScreensAction
{
    public function handle(int $tenantId): SenseReadingCatalog
    {
        $points = SensePoint::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit.location', 'screen.clockPoint.location'])
            ->get();

        $screens = [];
        foreach ($points as $point) {
            $choice = $this->choice($point);
            if ($choice !== null) {
                $screens[] = $choice;
            }
        }

        usort($screens, function (SenseReadingScreenChoice $a, SenseReadingScreenChoice $b): int {
            $byLocation = strcasecmp($a->locationName, $b->locationName);
            if ($byLocation !== 0) {
                return $byLocation;
            }

            return strcasecmp($a->name, $b->name);
        });

        $locations = [];
        foreach ($screens as $screen) {
            $locations[$screen->locationId] = [
                'id' => $screen->locationId,
                'name' => $screen->locationName,
            ];
        }

        return new SenseReadingCatalog(array_values($locations), $screens);
    }

    private function choice(SensePoint $point): ?SenseReadingScreenChoice
    {
        if ($point->unit !== null) {
            $location = $point->unit->location;

            return new SenseReadingScreenChoice(
                sensePointId: (int) $point->id,
                locationId: (int) ($location?->id ?? 0),
                locationName: $location !== null ? $location->localizedName() : __('sense.readings.no_location'),
                name: SenseBands::roomLabel($point->unit),
                unitId: (int) $point->unit_id,
                clockPointId: null,
            );
        }

        $clockPoint = $point->screen?->clockPoint;
        if ($clockPoint === null) {
            return null;
        }

        $location = $clockPoint->location;

        return new SenseReadingScreenChoice(
            sensePointId: (int) $point->id,
            locationId: (int) ($location?->id ?? 0),
            locationName: $location !== null ? $location->localizedName() : __('sense.readings.no_location'),
            name: (string) $clockPoint->name,
            unitId: null,
            clockPointId: (int) $clockPoint->id,
        );
    }
}
