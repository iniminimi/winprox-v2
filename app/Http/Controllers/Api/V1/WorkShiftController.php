<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Time\ApplyRequiredBreakToWorkShiftAction;
use App\Actions\Time\ClearWorkerClockDeviceAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\EndWorkVisitAction;
use App\Actions\Time\ManualClockInWorkShiftAction;
use App\Actions\Time\StartWorkVisitAction;
use App\Enums\ClockSource;
use App\Http\Requests\Time\ApiClockInRequest;
use App\Http\Requests\Time\ApiClockOutRequest;
use App\Http\Requests\Time\ApiEndWorkVisitRequest;
use App\Http\Requests\Time\ApiStartWorkVisitRequest;
use App\Http\Requests\Time\ApplyRequiredBreakToWorkShiftRequest;
use App\Http\Requests\Time\ManualClockInWorkShiftRequest;
use App\Http\Resources\WorkerResource;
use App\Http\Resources\WorkShiftResource;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Unit;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
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

    public function manualClockIn(ManualClockInWorkShiftRequest $request, ManualClockInWorkShiftAction $manualClockIn): JsonResponse
    {
        $this->authorize('manualClockIn', WorkShift::class);

        $validated = $request->validated();
        $worker = Worker::query()->findOrFail($validated['worker_id']);
        $clockPoint = ClockPoint::query()->findOrFail($validated['clock_point_id']);

        try {
            $shift = $manualClockIn->handle(
                $worker,
                $clockPoint,
                $validated['reason'],
                (int) Tenancy::id(),
                auth()->id(),
                auth()->user()?->accessibleLocationIds(),
            );
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

    public function startVisit(ApiStartWorkVisitRequest $request, StartWorkVisitAction $startVisit): JsonResponse
    {
        $this->authorize('startWorkVisit', WorkShift::class);

        $validated = $request->validated();
        $worker = Worker::query()->findOrFail($validated['worker_id']);
        $place = isset($validated['unit_id'])
            ? Unit::query()->findOrFail($validated['unit_id'])
            : Location::query()->findOrFail($validated['location_id']);

        try {
            $visit = $startVisit->handle(
                $worker,
                $place,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                ClockSource::Api,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $shift = $visit->workShift()->first() ?? WorkShift::query()->findOrFail($visit->work_shift_id);

        return $this->item(new WorkShiftResource($shift->load(['worker', 'team', 'clockInClockPoint'])), 201);
    }

    public function endVisit(ApiEndWorkVisitRequest $request, EndWorkVisitAction $endVisit): JsonResponse
    {
        $validated = $request->validated();
        $worker = Worker::query()->findOrFail($validated['worker_id']);

        $openShift = WorkShift::query()
            ->where('worker_id', $worker->id)
            ->open()
            ->first();

        if ($openShift === null) {
            return response()->json(['message' => 'shift_not_open'], 422);
        }

        $this->authorize('endWorkVisit', $openShift);

        $lat = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $lng = isset($validated['longitude']) ? (float) $validated['longitude'] : null;

        try {
            $endVisit->handle($worker, required: true, latitude: $lat, longitude: $lng, source: ClockSource::Api);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->item(new WorkShiftResource($openShift->fresh(['worker', 'team', 'clockInClockPoint'])));
    }

    public function applyRequiredBreak(
        ApplyRequiredBreakToWorkShiftRequest $request,
        WorkShift $workShift,
        ApplyRequiredBreakToWorkShiftAction $apply,
    ): JsonResponse {
        $this->authorize('applyRequiredBreak', $workShift);

        try {
            $request->validated();
            $shift = $apply->handle($workShift, (int) Tenancy::id(), auth()->id());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->item(new WorkShiftResource($shift));
    }

    public function releaseClockDevice(Worker $worker, ClearWorkerClockDeviceAction $clear): JsonResponse
    {
        $this->authorize('clearClockDevice', $worker);

        try {
            $updated = $clear->handle($worker, (int) Tenancy::id(), auth()->id());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->item(new WorkerResource($updated));
    }
}
