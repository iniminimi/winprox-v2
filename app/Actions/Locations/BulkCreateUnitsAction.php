<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitBulkBatch;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitBulkNaming;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BulkCreateUnitsAction
{
    private const MAX_UNITS = 500;

    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{batch: UnitBulkBatch, created: int}
     */
    public function handle(Location $location, array $data, int $tenantId, ?int $actorUserId = null): array
    {
        $floorCount = (int) $data['floors'];
        $roomsPerFloor = (int) $data['rooms_per_floor'];
        $scheme = (string) $data['scheme'];
        $prefix = trim((string) ($data['prefix'] ?? ''));

        $configError = UnitBulkNaming::validateConfig($floorCount, $roomsPerFloor, $scheme);
        if ($configError !== null) {
            throw new InvalidArgumentException($configError);
        }

        try {
            $names = UnitBulkNaming::generate($floorCount, $roomsPerFloor, $scheme, $prefix);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        $existingNames = Unit::query()
            ->where('location_id', $location->id)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $names = array_values(array_diff($names, $existingNames));
        $total = count($names);

        if ($total === 0) {
            throw new InvalidArgumentException('names_exist');
        }

        if ($total > self::MAX_UNITS) {
            throw new InvalidArgumentException('too_many');
        }

        Tenant::query()->findOrFail($tenantId)->assertCanAddUnits($total);

        $teamId = isset($data['default_internal_team_id']) && $data['default_internal_team_id'] !== ''
            ? (int) $data['default_internal_team_id']
            : null;

        return DB::transaction(function () use (
            $location,
            $tenantId,
            $names,
            $total,
            $prefix,
            $scheme,
            $floorCount,
            $roomsPerFloor,
            $teamId,
            $actorUserId,
        ): array {
            $batch = UnitBulkBatch::create([
                'tenant_id' => $tenantId,
                'location_id' => $location->id,
                'prefix' => $prefix !== '' ? $prefix : null,
                'scheme' => $scheme,
                'floors' => $floorCount,
                'rooms_per_floor' => $roomsPerFloor,
                'internal_team_id' => $teamId,
                'units_count' => $total,
            ]);

            foreach ($names as $name) {
                Unit::create([
                    'tenant_id' => $tenantId,
                    'location_id' => $location->id,
                    'bulk_batch_id' => $batch->id,
                    'name' => $name,
                    'default_internal_team_id' => $teamId,
                    'is_active' => true,
                ]);
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'unit_bulk.created',
                modelType: UnitBulkBatch::class,
                modelId: (int) $batch->id,
                payload: ['id' => $batch->id, 'created' => $total, 'location_id' => $location->id],
            );

            return ['batch' => $batch, 'created' => $total];
        });
    }
}
