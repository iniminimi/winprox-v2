<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\Unit;

class CreateUnitAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Location $location, array $data, int $tenantId): Unit
    {
        return Unit::create([
            'tenant_id' => $tenantId,
            'location_id' => $location->id,
            'name' => trim((string) $data['name']),
            'default_internal_team_id' => $data['default_internal_team_id'] ?? null,
            'is_active' => true,
        ]);
    }
}
