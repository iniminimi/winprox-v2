<?php

namespace App\Actions\Units;

use App\Actions\Locations\DeleteUnitAction;
use App\Data\Units\DeleteImportBatchData;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitDeletionGuard;
use Illuminate\Support\Facades\DB;

class DeleteImportBatchAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteUnitAction $deleteUnit,
    ) {}

    /**
     * @return array{success: bool, deleted_count?: int, preserved_count?: int, total_count?: int, errors?: list<string>}
     */
    public function handle(DeleteImportBatchData $data, int $tenantId, ?int $actorUserId = null): array
    {
        DB::beginTransaction();
        try {
            $allUnits = Unit::where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->get();

            $totalCount = $allUnits->count();

            if ($totalCount === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'errors' => ['Geen units gevonden voor deze import batch.'],
                ];
            }

            $unitsToDelete = Unit::where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->whereDoesntHave('issues')
                ->whereDoesntHave('issues.tasks')
                ->get();

            $deletedCount = 0;

            foreach ($unitsToDelete as $unit) {
                if (UnitDeletionGuard::blockReason($unit) !== null) {
                    continue;
                }

                $this->deleteUnit->handle($unit, $actorUserId);
                $deletedCount++;
            }

            $preservedCount = $totalCount - $deletedCount;

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'units.delete_import_batch',
                modelType: Unit::class,
                modelId: null,
                payload: [
                    'batch_id' => $data->importBatchId,
                    'deleted_count' => $deletedCount,
                    'preserved_count' => $preservedCount,
                    'preserved_reason' => 'has_issues_or_tasks',
                ],
            );

            DB::commit();

            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'preserved_count' => $preservedCount,
                'total_count' => $totalCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors' => ['Er is een databasefout opgetreden tijdens het verwijderen: '.$e->getMessage()],
            ];
        }
    }
}
