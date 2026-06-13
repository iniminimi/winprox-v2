<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Units\RecordUnitGpsReportAction;
use App\Data\Units\RecordUnitGpsReportData;
use App\Http\Requests\Units\RecordUnitGpsReportRequest;
use App\Http\Resources\UnitGpsReportResource;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;

class UnitGpsReportController extends Controller
{
    public function store(
        RecordUnitGpsReportRequest $request,
        Unit $unit,
        RecordUnitGpsReportAction $recordUnitGpsReport,
    ): JsonResponse {
        $this->authorize('updateGps', $unit);

        $report = $recordUnitGpsReport->handle(
            unit: $unit,
            data: RecordUnitGpsReportData::fromValidated($request->validated()),
            tenantId: Tenancy::id(),
            actorUserId: (int) auth()->id(),
        );

        return $this->success(new UnitGpsReportResource($report), 201);
    }
}
