<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Units\ImportUnitsAction;
use App\Data\Units\ImportUnitsData;
use App\Http\Requests\Units\ImportUnitsRequest;
use App\Http\Resources\UnitResource;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        return $this->paginated(
            UnitResource::collection(
                Unit::query()->with(['location', 'translations'])->orderBy('name')->paginate(50)
            )
        );
    }

    public function import(ImportUnitsRequest $request, ImportUnitsAction $importUnits): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $location = Location::query()
            ->where('tenant_id', Tenancy::id())
            ->whereKey((int) $request->validated('location_id'))
            ->firstOrFail();

        $this->authorize('update', $location);

        $dto = new ImportUnitsData(
            filePath: $request->file('file')->getRealPath(),
            originalName: $request->file('file')->getClientOriginalName(),
            locationId: (int) $location->id,
        );

        $result = $importUnits->handle(
            $dto,
            Tenancy::id(),
            (int) auth()->id()
        );

        if ($result['success']) {
            return $this->success([
                'message' => 'Import successful',
                'count' => $result['count'],
            ]);
        }

        return $this->error([
            'message' => 'Import failed',
            'errors' => $result['errors'],
        ], 422);
    }
}
