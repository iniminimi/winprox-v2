<?php

namespace App\Actions\Locations;

use App\Models\UnitBulkBatch;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitBulkBatchRegistry;
use App\Support\Units\UnitDeletionGuard;
use Illuminate\Support\Facades\DB;

class DeleteUnitBulkBatchAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteUnitAction $deleteUnit,
    ) {}

    /**
     * @return array{deleted: int, skipped: int}
     */
    public function handle(UnitBulkBatch $batch, ?int $actorUserId = null): array
    {
        $totalBefore = (int) $batch->units()->count();
        $units = UnitBulkBatchRegistry::deletableUnitsQuery($batch)->orderBy('id')->get();
        $deleted = 0;

        DB::transaction(function () use ($units, $actorUserId, &$deleted): void {
            foreach ($units as $unit) {
                if (UnitDeletionGuard::blockReason($unit) !== null) {
                    continue;
                }

                $this->deleteUnit->handle($unit, $actorUserId);
                $deleted++;
            }
        });

        $result = [
            'deleted' => $deleted,
            'skipped' => max(0, $totalBefore - $deleted),
        ];

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
