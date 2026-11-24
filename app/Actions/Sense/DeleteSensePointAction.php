<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;

class DeleteSensePointAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(SensePoint $point, int $tenantId, ?int $actorUserId = null): void
    {
        $id = (int) $point->id;
        $unitId = (int) $point->unit_id;
        $point->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'sense_point.deleted',
            modelType: SensePoint::class,
            modelId: $id,
            payload: ['unit_id' => $unitId],
        );
    }
}
