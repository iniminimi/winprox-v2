<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Models\Unit;
use App\Models\UnitMeasureField;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class SyncUnitMeasureFieldsAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  list<int>  $fieldIds
     */
    public function handle(Unit $unit, array $fieldIds, ?int $actorUserId = null): Unit
    {
        $tenantId = (int) $unit->tenant_id;
        $uniqueIds = array_values(array_unique(array_map('intval', $fieldIds)));

        if ($uniqueIds !== []) {
            $count = UnitMeasureField::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereIn('id', $uniqueIds)
                ->count();

            if ($count !== count($uniqueIds)) {
                throw ValidationException::withMessages([
                    'measure_field_ids' => [__('unit_measurements.errors.fields_invalid')],
                ]);
            }
        }

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
