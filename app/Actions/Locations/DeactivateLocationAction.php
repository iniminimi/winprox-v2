<?php

namespace App\Actions\Locations;

use App\Models\Location;

class DeactivateLocationAction
{
    public function handle(Location $location): Location
    {
        $location->update(['is_active' => false]);

        return $location->fresh();
    }
}
