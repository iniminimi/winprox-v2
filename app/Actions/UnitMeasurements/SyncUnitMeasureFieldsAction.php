<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Http\Requests\UnitMeasurements\SyncUnitMeasureFieldsRequest;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class SyncUnitMeasureFieldsAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  list<int>  $fieldIds
     */
    public function handle(Unit $unit, array $fieldIds, ?int $actorUserId = null): Unit
    {
        $tenantId = (int) $unit->tenant_id;
        $uniqueIds = SyncUnitMeasureFieldsRequest::assertActiveFieldIdsForTenant($tenantId, $fieldIds);

        $unit->measureFields()->sync($uniqueIds);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit.measure_fields_synced',
            modelType: Unit::class,
            modelId: (int) $unit->id,
            payload: ['unit_id' => $unit->id, 'field_ids' => $uniqueIds],
        );

        return $unit->fresh(['measureFields']);
    }
}
