<?php

namespace App\Support\Team;

use App\Models\Unit;
use Illuminate\Validation\ValidationException;

final class WorkerDefaultUnit
{
    /**
     * @param  list<int>  $locationIds
     */
    public static function normalize(?int $unitId, int $tenantId, array $locationIds, bool $clocksAllLocations): ?int
    {
        if ($unitId === null || $unitId === 0) {
            return null;
        }

        $unit = Unit::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($unitId)
            ->where('is_active', true)
            ->whereNotNull('roster_code')
            ->where('roster_code', '!=', '')
            ->first();

        if ($unit === null) {
            throw ValidationException::withMessages([
                'default_unit_id' => [__('team.errors.worker_default_unit_invalid')],
            ]);
        }

        if (! $clocksAllLocations && ! in_array((int) $unit->location_id, $locationIds, true)) {
            throw ValidationException::withMessages([
                'default_unit_id' => [__('team.errors.worker_default_unit_location')],
            ]);
        }

        return (int) $unit->id;
    }
}
