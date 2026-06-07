<?php

namespace App\Actions\Units;

use App\Data\Units\DeleteImportBatchData;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class DeleteImportBatchAction
{
    /**
     * Delete units from an import batch, preserving units with issues or tasks.
     *
     * @param DeleteImportBatchData $data
     * @param int $tenantId
     * @param int|null $actorUserId
     * @return array
     */
    public function handle(DeleteImportBatchData $data, int $tenantId, ?int $actorUserId = null): array
    {
        DB::beginTransaction();
        try {
            // Find all units in this batch for this tenant
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

            // Find units WITHOUT issues or tasks (safe to delete)
            $unitsToDelete = Unit::where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->whereDoesntHave('issues')
                ->whereDoesntHave('issues.tasks')
                ->get();

            $deletedCount = $unitsToDelete->count();
            $preservedCount = $totalCount - $deletedCount;

            // Delete safe units
            foreach ($unitsToDelete as $unit) {
                $unit->delete();
            }

            // Audit logging
            $this->logAudit($tenantId, $actorUserId, $data->importBatchId, $deletedCount, $preservedCount);

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
                'errors' => ['Er is een databasefout opgetreden tijdens het verwijderen: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Log audit entry for the batch deletion.
     */
    protected function logAudit(int $tenantId, ?int $actorUserId, string $batchId, int $deletedCount, int $preservedCount): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $actorUserId,
            'action' => 'units.delete_import_batch',
            'model_type' => Unit::class,
            'model_id' => null,
            'payload' => json_encode([
                'batch_id' => $batchId,
                'deleted_count' => $deletedCount,
                'preserved_count' => $preservedCount,
                'preserved_reason' => 'has_issues_or_tasks',
            ]),
            'created_at' => now(),
        ]);
    }
}
