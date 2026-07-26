<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Tenant;

/**
 * Eerste actieve Clock Point van de tenant (zelfde volgorde als Teams-default).
 */
class ResolveDefaultClockPointAction
{
    public function handle(Tenant $tenant): ?ClockPoint
    {
        return ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();
    }
}
