<?php

namespace App\Actions\Locations;

use App\Models\UnitBulkBatch;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitBulkBatchRegistry;

class DeleteUnitBulkBatchAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array{deleted: int, skipped: int}
     */
    public function handle(UnitBulkBatch $batch, ?int $actorUserId = null): array
    {
        $result = UnitBulkBatchRegistry::deleteDeletableUnitsInBatch($batch);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $batch->tenant_id,
            action: 'unit_bulk.deleted',
            modelType: UnitBulkBatch::class,
            modelId: (int) $batch->id,
            payload: [
                'id' => $batch->id,
                'deleted' => $result['deleted'],
                'skipped' => $result['skipped'],
            ],
        );

        return $result;
    }
}
