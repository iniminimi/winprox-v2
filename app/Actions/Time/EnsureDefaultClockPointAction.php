<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Tenant;

class EnsureDefaultClockPointAction
{
    public function __construct(private CreateClockPointAction $createClockPoint) {}

    public function handle(Tenant $tenant, string $defaultName, ?int $actorUserId): ClockPoint
    {
        $existing = ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createClockPoint->handle($tenant, [
            'name' => trim($defaultName),
            'is_active' => true,
            'sort_order' => 0,
        ], $actorUserId);
    }
}
