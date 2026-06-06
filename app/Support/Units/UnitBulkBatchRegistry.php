<?php

declare(strict_types=1);

namespace App\Support\Units;

use App\Models\Location;
use App\Models\Unit;
use App\Models\UnitBulkBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UnitBulkBatchRegistry
{
    public const RECENT_BATCH_LIMIT = 10;

    public const RECENT_BATCH_DAYS = 30;

    /**
     * @return Collection<int, UnitBulkBatch>
     */
    public static function recentBatchesForLocation(Location $location): Collection
    {
        return UnitBulkBatch::query()
            ->where('location_id', $location->id)
            ->where('tenant_id', $location->tenant_id)
            ->where('created_at', '>=', now()->subDays(self::RECENT_BATCH_DAYS))
            ->whereHas('units')
            ->with([
                'internalTeam:id,name',
                'units' => fn ($query) => $query
                    ->select('id', 'bulk_batch_id', 'name')
                    ->orderBy('name'),
            ])
            ->withCount('units')
            ->orderByDesc('id')
            ->limit(self::RECENT_BATCH_LIMIT)
            ->get();
    }

    /**
     * @return array{
     *     total: int,
     *     deletable: int,
     *     blocked: int,
     *     first_name: string|null,
     *     last_name: string|null,
     *     can_delete: bool,
     * }
     */
    public static function summary(UnitBulkBatch $batch): array
    {
        $units = $batch->relationLoaded('units')
            ? $batch->units
            : $batch->units()->orderBy('name')->get(['id', 'name', 'bulk_batch_id']);

        $total = $units->count();
        $deletable = self::deletableUnitsQuery($batch)->count();
        $firstName = $units->first()?->name;
        $lastName = $units->last()?->name;

        return [
            'total' => $total,
            'deletable' => $deletable,
            'blocked' => max(0, $total - $deletable),
            'first_name' => is_string($firstName) ? $firstName : null,
            'last_name' => is_string($lastName) ? $lastName : null,
            'can_delete' => $deletable > 0,
        ];
    }

    /**
     * @return array{deleted: int, skipped: int}
     */
    public static function deleteDeletableUnitsInBatch(UnitBulkBatch $batch): array
    {
        $totalBefore = (int) $batch->units()->count();
        $units = self::deletableUnitsQuery($batch)->orderBy('id')->get();

        $deleted = 0;

        DB::transaction(function () use ($units, &$deleted): void {
            foreach ($units as $unit) {
                if (UnitDeletionGuard::blockReason($unit) !== null) {
                    continue;
                }

                $unit->delete();
                $deleted++;
            }
        });

        return [
            'deleted' => $deleted,
            'skipped' => max(0, $totalBefore - $deleted),
        ];
    }

    /**
     * @return Builder<Unit>
     */
    public static function deletableUnitsQuery(UnitBulkBatch $batch): Builder
    {
        return Unit::query()
            ->where('bulk_batch_id', $batch->id)
            ->whereDoesntHave('issues');
    }
}
