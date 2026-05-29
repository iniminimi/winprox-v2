<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LocationResource;
use App\Models\Location;
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
}
