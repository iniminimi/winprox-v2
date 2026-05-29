<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        return $this->paginated(
            UnitResource::collection(
                Unit::query()->with('location')->orderBy('name')->paginate(50)
            )
        );
    }
}
