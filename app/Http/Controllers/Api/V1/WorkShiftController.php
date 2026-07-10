<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\WorkShiftResource;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkShift::class);

        $query = WorkShift::query()
            ->with(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('worker_id'), fn ($q, $workerId) => $q->where('worker_id', $workerId))
            ->orderByDesc('clock_in_at');

        return $this->paginated(WorkShiftResource::collection($query->paginate(50)));
    }
}
