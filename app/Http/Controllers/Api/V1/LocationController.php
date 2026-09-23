<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Locations\ImportLocationsAction;
use App\Data\Locations\ImportLocationsData;
use App\Http\Requests\Locations\ImportLocationsRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Location::class);

        return $this->paginated(
            LocationResource::collection(Location::query()->orderBy('name')->paginate(50))
        );
    }

    public function import(ImportLocationsRequest $request, ImportLocationsAction $importLocations): JsonResponse
    {
        $this->authorize('create', Location::class);

        $dto = new ImportLocationsData(
            filePath: $request->file('file')->getRealPath(),
            originalName: $request->file('file')->getClientOriginalName(),
        );

        $result = $importLocations->handle(
            $dto,
            (int) Tenancy::id(),
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
