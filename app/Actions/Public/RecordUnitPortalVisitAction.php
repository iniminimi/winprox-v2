<?php

namespace App\Actions\Public;

use App\Models\Unit;
use App\Models\UnitPortalVisit;

class RecordUnitPortalVisitAction
{
    public function handle(Unit $unit, ?string $ipAddress = null): void
    {
        if (! $unit->is_active) {
            return;
        }

        UnitPortalVisit::create([
            'tenant_id' => $unit->tenant_id,
            'unit_id' => $unit->id,
            'ip_address' => $ipAddress,
            'visited_at' => now(),
        ]);
    }
}
