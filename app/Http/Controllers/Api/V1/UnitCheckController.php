<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Units\IngestUnitCheckByExternalIdAction;
use App\Actions\Units\RecordUnitCheckAction;
use App\Data\Units\RecordUnitCheckData;
use App\Enums\UnitCheckSource;
use App\Http\Requests\Units\IngestUnitCheckByExternalIdRequest;
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

        return $this->item(new UnitCheckResource($check), 201);
    }

    /**
     * Inbound check from external facility software, keyed by unit external_id.
     */
    public function storeByExternalId(
        IngestUnitCheckByExternalIdRequest $request,
        IngestUnitCheckByExternalIdAction $ingest,
    ): JsonResponse {
        $validated = $request->validated();
        $tenantId = Tenancy::id();

        $unit = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('external_id', trim((string) $validated['external_unit_id']))
            ->first();

        if ($unit !== null) {
            $this->authorize('view', $unit);
        }

        $check = $ingest->handle(
            validated: $validated,
            tenantId: $tenantId,
            actorUserId: (int) auth()->id(),
        );

        $status = $check->wasRecentlyCreated ? 201 : 200;

        return $this->item(new UnitCheckResource($check), $status);
    }
}
