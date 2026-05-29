<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TeamResource;
use App\Models\InternalTeam;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', InternalTeam::class);

        return $this->paginated(
            TeamResource::collection(
                InternalTeam::query()->orderBy('sort_order')->orderBy('name')->paginate(50)
            )
        );
    }
}
