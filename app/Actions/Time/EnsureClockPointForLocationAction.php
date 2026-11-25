<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use InvalidArgumentException;

/** Maakt het Clock Point van een locatie aan wanneer die er nog geen actief heeft. */
class EnsureClockPointForLocationAction
{
    public function __construct(private CreateClockPointAction $createClockPoint) {}

    public function handle(Tenant $tenant, Location $location, ?int $actorUserId): ClockPoint
    {
        if ((int) $location->tenant_id !== (int) $tenant->id || ! $location->is_active) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $existing = ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $name = mb_substr(trim($location->localizedName()), 0, 120);
        if ($name === '') {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return $this->createClockPoint->handle($tenant, [
            'name' => $name,
            'location_id' => $location->id,
            'is_active' => true,
            'sort_order' => 0,
        ], $actorUserId);
    }
}
