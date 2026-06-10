<?php

namespace App\Actions\Workers;

use App\Actions\Team\DeleteWorkerAction;
use App\Data\Workers\DeleteWorkerImportBatchData;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class DeleteWorkerImportBatchAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteWorkerAction $deleteWorker,
    ) {}

    /**
     * @return array{success: bool, deleted_count?: int, preserved_count?: int, total_count?: int, errors?: list<string>}
     */
    public function handle(DeleteWorkerImportBatchData $data, int $tenantId, ?int $actorUserId = null): array
    {
        DB::beginTransaction();
        try {
            $allWorkers = Worker::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->get();

            $totalCount = $allWorkers->count();

            if ($totalCount === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'errors'  => ['Geen uitvoerders gevonden voor deze import batch.'],
                ];
            }

            $workersToDelete = Worker::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->whereDoesntHave('devices')
                ->get();

            $deletedCount = 0;

            foreach ($workersToDelete as $worker) {
                $this->deleteWorker->handle($worker, $actorUserId);
                $deletedCount++;
            }

            $preservedCount = $totalCount - $deletedCount;

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'workers.delete_import_batch',
                modelType: Worker::class,
                modelId: null,
                payload: [
                    'batch_id'         => $data->importBatchId,
                    'deleted_count'    => $deletedCount,
                    'preserved_count'  => $preservedCount,
                    'preserved_reason' => 'has_devices',
                ],
            );

            DB::commit();

            return [
                'success'         => true,
                'deleted_count'   => $deletedCount,
                'preserved_count' => $preservedCount,
                'total_count'     => $totalCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors'  => ['Er is een databasefout opgetreden tijdens het verwijderen: '.$e->getMessage()],
            ];
        }
    }
}
