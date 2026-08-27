<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Models\UnitMeasureField;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class SetUnitMeasureFieldActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(UnitMeasureField $field, bool $isActive, ?int $actorUserId = null): UnitMeasureField
    {
        if ((bool) $field->is_active === $isActive) {
            return $field;
        }

        $field->update(['is_active' => $isActive]);
        $fresh = $field->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: $isActive ? 'unit_measure_field.activated' : 'unit_measure_field.deactivated',
            modelType: UnitMeasureField::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name, 'is_active' => $fresh->is_active],
        );

        return $fresh;
    }
}
