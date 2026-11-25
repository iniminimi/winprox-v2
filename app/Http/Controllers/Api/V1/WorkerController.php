<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\WorkerResource;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;

class WorkerController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Worker::class);

        $viewer = auth()->user();

        return $this->paginated(
            WorkerResource::collection(
                Worker::query()
                    ->when($viewer instanceof User, fn ($query) => $query->visibleTo($viewer))
                    ->with('team')
                    ->orderBy('first_name')
                    ->paginate(50)
            )
        );
    }
}
