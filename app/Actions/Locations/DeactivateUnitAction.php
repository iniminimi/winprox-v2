<?php

namespace App\Actions\Locations;

use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class DeactivateUnitAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, ?int $actorUserId = null): Unit
    {
        $unit->update(['is_active' => false]);

        $fresh = $unit->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'unit.deactivated',
            modelType: Unit::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name],
        );

        return $fresh;
    }
}
