<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class CreateUnitAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Location $location, array $data, int $tenantId, ?int $actorUserId = null): Unit
    {
        Tenant::query()->findOrFail($tenantId)->assertCanAddUnits(1);

        $unit = Unit::create([
            'tenant_id' => $tenantId,
            'location_id' => $location->id,
            'name' => trim((string) $data['name']),
            'default_internal_team_id' => $data['default_internal_team_id'] ?? null,
            'is_active' => true,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit.created',
            modelType: Unit::class,
            modelId: (int) $unit->id,
            payload: ['id' => $unit->id, 'name' => $unit->name, 'location_id' => $unit->location_id],
        );

        return $unit;
    }
}
