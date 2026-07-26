<?php

namespace App\Actions\Units;

use App\Actions\Locations\DeleteCategoryAction;
use App\Actions\Locations\DeleteLocationAction;
use App\Actions\Locations\DeleteUnitAction;
use App\Data\Units\DeleteImportBatchData;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitDeletionGuard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteImportBatchAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteUnitAction $deleteUnit,
        private DeleteLocationAction $deleteLocation,
        private DeleteCategoryAction $deleteCategory,
    ) {}

    /**
     * @return array{success: bool, deleted_count?: int, deleted_location_count?: int, deleted_category_count?: int, preserved_count?: int, total_count?: int, errors?: list<string>}
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

            $locationIdsFromBatch = $allUnits->pluck('location_id')->unique()->filter()->values()->all();
            $categoryIdsFromBatch = $allUnits->pluck('category_id')->unique()->filter()->values()->all();
            $categoriesEligibleForCleanup = $this->categoryIdsWithOnlyBatchUnits($tenantId, $data->importBatchId, $categoryIdsFromBatch);
            $createdLocationIds = $this->createdLocationIdsFromImportAudit($tenantId, $data->importBatchId);
            $createdCategoryIds = $this->createdCategoryIdsFromImportAudit($tenantId, $data->importBatchId);

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
            // Only remove locations that the import itself created — never the
            // location the user imported into (location-scoped CSV).
            $deletedLocationCount = $this->deleteEmptyImportLocations(
                $tenantId,
                $createdLocationIds,
                $actorUserId,
            );
            $deletedCategoryCount = $this->deleteEmptyImportCategories(
                $tenantId,
                $createdCategoryIds !== [] ? $createdCategoryIds : $categoriesEligibleForCleanup,
                $actorUserId,
            );

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'units.delete_import_batch',
                modelType: Unit::class,
                modelId: null,
                payload: [
                    'batch_id' => $data->importBatchId,
                    'deleted_count' => $deletedCount,
                    'deleted_location_count' => $deletedLocationCount,
                    'deleted_category_count' => $deletedCategoryCount,
                    'preserved_count' => $preservedCount,
                    'preserved_reason' => 'has_issues_or_tasks',
                ],
            );

            DB::commit();

            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'deleted_location_count' => $deletedLocationCount,
                'deleted_category_count' => $deletedCategoryCount,
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

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function categoryIdsWithOnlyBatchUnits(int $tenantId, string $batchId, array $categoryIds): array
    {
        $eligible = [];

        foreach ($categoryIds as $categoryId) {
            $hasNonBatchUnits = Unit::where('tenant_id', $tenantId)
                ->where('category_id', $categoryId)
                ->where(function ($query) use ($batchId) {
                    $query->where('import_batch_id', '!=', $batchId)
                        ->orWhereNull('import_batch_id');
                })
                ->exists();

            if (! $hasNonBatchUnits) {
                $eligible[] = (int) $categoryId;
            }
        }

        return $eligible;
    }

    /**
     * @return array{locations: list<int>, categories: list<int>}
     */
    private function createdIdsFromImportAudit(int $tenantId, string $batchId): array
    {
        $log = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'units.import')
            ->orderByDesc('id')
            ->get()
            ->first(function (AuditLog $entry) use ($batchId) {
                $payload = $this->payloadArray($entry->payload);

                return ($payload['batch_id'] ?? null) === $batchId;
            });

        if ($log === null) {
            return ['locations' => [], 'categories' => []];
        }

        $payload = $this->payloadArray($log->payload);

        return [
            'locations' => $this->intIdList($payload['created_location_ids'] ?? []),
            'categories' => $this->intIdList($payload['created_category_ids'] ?? []),
        ];
    }

    /**
     * @return list<int>
     */
    private function createdLocationIdsFromImportAudit(int $tenantId, string $batchId): array
    {
        return $this->createdIdsFromImportAudit($tenantId, $batchId)['locations'];
    }

    /**
     * @return list<int>
     */
    private function createdCategoryIdsFromImportAudit(int $tenantId, string $batchId): array
    {
        return $this->createdIdsFromImportAudit($tenantId, $batchId)['categories'];
    }

    /**
     * @param  list<int>  $locationIds
     */
    private function deleteEmptyImportLocations(int $tenantId, array $locationIds, ?int $actorUserId): int
    {
        $deletedLocationCount = 0;

        foreach ($locationIds as $locationId) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->find($locationId);

            if ($location === null || $location->units()->exists()) {
                continue;
            }

            try {
                $this->deleteLocation->handle($location, $actorUserId);
                $deletedLocationCount++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $deletedLocationCount;
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function deleteEmptyImportCategories(int $tenantId, array $categoryIds, ?int $actorUserId): int
    {
        $deletedCategoryCount = 0;

        foreach ($categoryIds as $categoryId) {
            $category = Category::query()
                ->where('tenant_id', $tenantId)
                ->find($categoryId);

            if ($category === null || $category->units()->exists()) {
                continue;
            }

            try {
                $this->deleteCategory->handle($category, $actorUserId);
                $deletedCategoryCount++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $deletedCategoryCount;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadArray(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload)) {
            return json_decode($payload, true) ?? [];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function intIdList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($ids, fn ($id) => is_numeric($id))));
    }
}
