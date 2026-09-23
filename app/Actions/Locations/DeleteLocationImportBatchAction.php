<?php

namespace App\Actions\Locations;

use App\Data\Locations\DeleteLocationImportBatchData;
use App\Models\Location;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteLocationImportBatchAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteLocationAction $deleteLocation,
    ) {}

    /**
     * @return array{success: bool, deleted_count?: int, preserved_count?: int, total_count?: int, errors?: list<string>}
     */
    public function handle(DeleteLocationImportBatchData $data, int $tenantId, ?int $actorUserId = null): array
    {
        DB::beginTransaction();
        try {
            $allLocations = Location::query()
                ->where('tenant_id', $tenantId)
                ->where('import_batch_id', $data->importBatchId)
                ->get();

            $totalCount = $allLocations->count();

            if ($totalCount === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'errors' => [__('locations.locations_import_history.nothing_deletable')],
                ];
            }

            $deletedCount = 0;

            foreach ($allLocations as $location) {
                try {
                    $this->deleteLocation->handle($location, $actorUserId);
                    $deletedCount++;
                } catch (InvalidArgumentException) {
                    continue;
                }
            }

            $preservedCount = $totalCount - $deletedCount;

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'locations.delete_import_batch',
                modelType: Location::class,
                modelId: null,
                payload: [
                    'batch_id' => $data->importBatchId,
                    'deleted_count' => $deletedCount,
                    'preserved_count' => $preservedCount,
                    'preserved_reason' => 'has_units_or_content',
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
                'errors' => [__('locations.locations_import_history.delete_failed')],
            ];
        }
    }
}
