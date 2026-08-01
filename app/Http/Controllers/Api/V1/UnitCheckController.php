<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Units\RecordUnitCheckAction;
use App\Data\Units\RecordUnitCheckData;
use App\Enums\UnitCheckSource;
use App\Http\Requests\Units\RecordUnitCheckRequest;
use App\Http\Resources\UnitCheckResource;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;

class UnitCheckController extends Controller
{
    public function store(
        RecordUnitCheckRequest $request,
        Unit $unit,
        RecordUnitCheckAction $recordUnitCheck,
    ): JsonResponse {
        $this->authorize('createCheck', $unit);

        $validated = $request->validated();
        $validated['source'] = UnitCheckSource::Api->value;

        $check = $recordUnitCheck->handle(
            unit: $unit,
            data: RecordUnitCheckData::fromValidated($validated),
            tenantId: Tenancy::id(),
            worker: null,
            actorUserId: (int) auth()->id(),
        );

        return $this->success(new UnitCheckResource($check), 201);
    }
}
