<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;

class ResolveTenantHomescreenPointAction
{
    /**
     * Canoniek startpunt van de geïnstalleerde app. Een punt zonder locatie
     * laat elke uitvoerder identificeren (canClockAt(null) = true); een
     * locatie-gebonden punt zou workers van andere vestigingen uitsluiten.
     */
    public function handle(int $tenantId): ?ClockPoint
    {
        return ClockPoint::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByRaw('case when location_id is null then 0 else 1 end')
            ->orderBy('id')
            ->first();
    }
}
