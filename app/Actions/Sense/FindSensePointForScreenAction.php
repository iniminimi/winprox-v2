<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Sense\SenseScreenRef;
use App\Models\Screen;
use App\Models\SensePoint;
use App\Support\Sense\SenseBands;

class FindSensePointForScreenAction
{
    public function handle(int $tenantId, ?int $unitId, ?int $clockPointId): ?SenseScreenRef
    {
        if ($unitId === null && $clockPointId === null) {
            return null;
        }

        $screen = Screen::query()
            ->where('tenant_id', $tenantId)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->when($clockPointId !== null, fn ($query) => $query->where('clock_point_id', $clockPointId))
            ->first();

        if ($screen === null) {
            return null;
        }

        $point = SensePoint::query()
            ->where('tenant_id', $tenantId)
            ->where('screen_id', $screen->id)
            ->with(['unit.location', 'screen.clockPoint'])
            ->first();

        if ($point === null) {
            return null;
        }

        $place = $point->unit !== null
            ? SenseBands::roomLabel($point->unit)
            : (string) ($point->screen?->clockPoint?->name ?? '');

        return new SenseScreenRef((int) $point->id, $place);
    }
}
