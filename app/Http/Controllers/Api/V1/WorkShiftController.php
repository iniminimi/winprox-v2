<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Enums\ClockSource;
use App\Http\Requests\Time\ApiClockInRequest;
use App\Http\Requests\Time\ApiClockOutRequest;
use App\Http\Resources\WorkShiftResource;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

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

    public function clockIn(ApiClockInRequest $request, ClockInAction $clockIn): JsonResponse
    {
        $this->authorize('clockIn', WorkShift::class);

        $validated = $request->validated();
        $worker = Worker::query()->findOrFail($validated['worker_id']);
        $clockPoint = ClockPoint::query()->findOrFail($validated['clock_point_id']);

        try {
            $shift = $clockIn->handle($worker, $clockPoint, source: ClockSource::Api);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->item(new WorkShiftResource($shift->load(['worker', 'team', 'clockInClockPoint'])), 201);
    }

    public function clockOut(ApiClockOutRequest $request, ClockOutAction $clockOut): JsonResponse
    {
        $validated = $request->validated();
        $worker = Worker::query()->findOrFail($validated['worker_id']);
        $clockPoint = ClockPoint::query()->findOrFail($validated['clock_point_id']);

        $openShift = WorkShift::query()
            ->where('worker_id', $worker->id)
            ->open()
            ->first();

        if ($openShift === null) {
            return response()->json(['message' => 'shift_not_open'], 422);
        }

        $this->authorize('clockOut', $openShift);

        try {
            $shift = $clockOut->handle($worker, $clockPoint, source: ClockSource::Api);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->item(new WorkShiftResource($shift));
    }
}
