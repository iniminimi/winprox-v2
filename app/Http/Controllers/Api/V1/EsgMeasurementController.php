<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Http\Requests\Esg\RecordEsgMeasurementRequest;
use App\Http\Resources\EsgMeasurementResource;
use App\Models\EsgIndicator;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;

class EsgMeasurementController extends Controller
{
    public function store(
        RecordEsgMeasurementRequest $request,
        RecordEsgMeasurementAction $record,
    ): JsonResponse {
        $tenantId = (int) Tenancy::id();
        $validated = $request->validated();
        $indicator = EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $validated['esg_indicator_id']);

        $measurement = $record->handle(
            RecordEsgMeasurementRequest::toData($validated, $indicator),
            $tenantId,
            isset($validated['worker_id']) ? (int) $validated['worker_id'] : null,
            (int) auth()->id(),
        );

        return $this->item(
            new EsgMeasurementResource($measurement->fresh(['indicator'])),
            201,
        );
    }
}
