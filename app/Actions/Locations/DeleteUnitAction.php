<?php

namespace App\Actions\Locations;

use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitDeletionGuard;
use InvalidArgumentException;

class DeleteUnitAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, ?int $actorUserId = null): void
    {
        $reason = UnitDeletionGuard::blockReason($unit);
        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }

        $tenantId = (int) $unit->tenant_id;
        $id = (int) $unit->id;
        $name = $unit->name;

        $unit->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit.deleted',
            modelType: Unit::class,
            modelId: $id,
            payload: ['id' => $id, 'name' => $name],
        );
    }
}
