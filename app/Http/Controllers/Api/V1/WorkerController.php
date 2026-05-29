<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\WorkerResource;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;

class WorkerController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Worker::class);

        return $this->paginated(
            WorkerResource::collection(
                Worker::query()->with('team')->orderBy('first_name')->paginate(50)
            )
        );
    }
}
