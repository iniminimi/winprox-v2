<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Maakt voor elke locatie zonder units een "Hele locatie"-unit.
 */
class EnsureSiteUnitsForEmptyLocationsAction
{
    public function __construct(
        private EnsureSiteUnitForLocationAction $ensureSiteUnit,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return array{created: int, skipped: int, empty_count: int}
     */
    public function handle(int $tenantId, ?int $actorUserId = null): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        $emptyLocations = Location::query()
            ->where('tenant_id', $tenantId)
            ->whereDoesntHave('units')
            ->orderBy('id')
            ->get();

        $emptyCount = $emptyLocations->count();
        if ($emptyCount === 0) {
            return ['created' => 0, 'skipped' => 0, 'empty_count' => 0];
        }

        try {
            $tenant->assertCanAddUnits($emptyCount);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('unit_limit_exceeded');
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($emptyLocations, $tenantId, $actorUserId, &$created, &$skipped): void {
            foreach ($emptyLocations as $location) {
                $unit = $this->ensureSiteUnit->handle($location, $tenantId, $actorUserId);
                if ($unit !== null) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'locations.ensure_site_units',
            modelType: Location::class,
            modelId: null,
            payload: [
                'created' => $created,
                'skipped' => $skipped,
                'empty_count' => $emptyCount,
            ],
        );

        return [
            'created' => $created,
            'skipped' => $skipped,
            'empty_count' => $emptyCount,
        ];
    }
}
