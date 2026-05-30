<?php

namespace App\Actions\Locations;

use App\Models\Unit;

class DeactivateUnitAction
{
    public function handle(Unit $unit): Unit
    {
        $unit->update(['is_active' => false]);

        return $unit->fresh();
    }
}
