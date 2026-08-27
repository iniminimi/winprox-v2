<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Data\UnitMeasurements\SaveUnitMeasureFieldData;
use App\Models\UnitMeasureField;
use App\Support\Audit\AuditRecorder;

class SaveUnitMeasureFieldAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        SaveUnitMeasureFieldData $data,
        int $tenantId,
        ?UnitMeasureField $field = null,
        ?int $actorUserId = null,
    ): UnitMeasureField {
        $payload = [
            'tenant_id' => $tenantId,
            'name' => $data->name,
            'type' => $data->type,
            'unit_of_measure' => $data->unitOfMeasure,
            'min_value' => $data->minValue,
            'max_value' => $data->maxValue,
            'options' => $data->options,
            'is_active' => $data->isActive,
        ];

        if ($field === null) {
            $field = UnitMeasureField::query()->create($payload);
            $action = 'unit_measure_field.created';
        } else {
            $field->update($payload);
            $field = $field->fresh();
            $action = 'unit_measure_field.updated';
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: $action,
            modelType: UnitMeasureField::class,
            modelId: (int) $field->id,
            payload: [
                'id' => $field->id,
                'name' => $field->name,
                'type' => $field->type->value,
            ],
        );

        return $field;
    }
}
