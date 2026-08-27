<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnitMeasurements\RecordUnitMeasurementAction;
use App\Data\UnitMeasurements\RecordUnitMeasurementData;
use App\Enums\UnitMeasurementSource;
use App\Http\Requests\UnitMeasurements\RecordUnitMeasurementRequest;
use App\Http\Resources\UnitMeasurementResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class UnitMeasurementController
{
    public function store(
        Unit $unit,
        RecordUnitMeasurementRequest $request,
        RecordUnitMeasurementAction $record,
    ): JsonResponse {
        $validated = $request->validated();
        $validated['source'] = UnitMeasurementSource::Api->value;

        $measurement = $record->handle(
            unit: $unit,
            data: RecordUnitMeasurementData::fromValidated($validated),
            tenantId: (int) $unit->tenant_id,
            actorUserId: $request->user()?->id ? (int) $request->user()->id : null,
        );

        return (new UnitMeasurementResource($measurement))
            ->response()
            ->setStatusCode(201);
    }
}
