<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Time\CopyWeekAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ListShiftTypesAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Actions\Time\SetShiftTypeActiveAction;
use App\Data\Time\CopyWeekData;
use App\Data\Time\PublishWeekData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\ShiftTypeColor;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Time\CopyWeekRequest;
use App\Http\Requests\Time\PublishWeekRequest;
use App\Http\Requests\Time\SavePlannedShiftsRequest;
use App\Http\Requests\Time\SaveShiftTypeRequest;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TimeScheduleController extends Controller
{
    public function show(Request $request, ListRosterWeekAction $list): JsonResponse
    {
        $this->authorize('viewAny', PlannedShift::class);

        $weekStart = (string) $request->query('week_start', now()->toDateString());
        $teamId = $request->query('team_id');
        $snapshot = $list->handle(
            (int) Tenancy::id(),
            $weekStart,
            $teamId !== null && $teamId !== '' ? (int) $teamId : null,
            $request->user(),
        );

        return $this->success($snapshot->toArray());
    }

    public function save(SavePlannedShiftsRequest $request, SavePlannedShiftsAction $save): JsonResponse
    {
        $this->authorize('update', PlannedShift::class);
        $validated = $request->validated();

        try {
            $shifts = $save->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new SavePlannedShiftsData(
                    $validated['week_start'],
                    array_map('intval', $validated['worker_ids']),
                    array_map(fn ($cell) => [
                        'worker_id' => (int) $cell['worker_id'],
                        'date' => (string) $cell['date'],
                        'raw' => (string) ($cell['raw'] ?? ''),
                    ], $validated['cells']),
                ),
                $request->user()?->id,
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            return response()->json([
                'message' => __($e->getMessage()),
                'cells' => $e instanceof RosterValidationException ? $e->cells : [],
            ], 422);
        }

        return $this->success(['count' => count($shifts)]);
    }

    public function copy(CopyWeekRequest $request, CopyWeekAction $copy): JsonResponse
    {
        $this->authorize('update', PlannedShift::class);
        $validated = $request->validated();

        try {
            $shifts = $copy->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new CopyWeekData(
                    $validated['source_week_start'],
                    $validated['target_week_start'],
                    array_map('intval', $validated['worker_ids']),
                ),
                $request->user()?->id,
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            return response()->json(['message' => __($e->getMessage())], 422);
        }

        return $this->success(['count' => count($shifts)]);
    }

    public function publish(PublishWeekRequest $request, PublishWeekAction $publish): JsonResponse
    {
        $this->authorize('publish', PlannedShift::class);
        $validated = $request->validated();

        try {
            $count = $publish->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new PublishWeekData(
                    $validated['week_start'],
                    array_map('intval', $validated['worker_ids']),
                ),
                $request->user()?->id,
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            return response()->json(['message' => __($e->getMessage())], 422);
        }

        return $this->success(['count' => $count]);
    }

    public function types(ListShiftTypesAction $list): JsonResponse
    {
        $this->authorize('viewAny', ShiftType::class);
        $types = $list->handle((int) Tenancy::id(), false);

        return $this->success(array_map(fn (ShiftType $type) => [
            'id' => $type->id,
            'code' => $type->code,
            'label' => $type->label,
            'start_time' => $type->start_time,
            'end_time' => $type->end_time,
            'break_minutes' => $type->break_minutes,
            'color' => $type->color->value,
            'is_active' => $type->is_active,
        ], $types));
    }

    public function storeType(SaveShiftTypeRequest $request, SaveShiftTypeAction $save): JsonResponse
    {
        $this->authorize('create', ShiftType::class);
        $type = $this->persistType($request, $save, null);

        return $this->success(['id' => $type->id], 201);
    }

    public function updateType(SaveShiftTypeRequest $request, ShiftType $shiftType, SaveShiftTypeAction $save): JsonResponse
    {
        $this->authorize('update', $shiftType);
        $type = $this->persistType($request, $save, (int) $shiftType->id);

        return $this->success(['id' => $type->id]);
    }

    public function setTypeActive(Request $request, ShiftType $shiftType, SetShiftTypeActiveAction $setActive): JsonResponse
    {
        $this->authorize('update', $shiftType);
        $active = $request->boolean('is_active');
        $type = $setActive->handle($shiftType, $active, $request->user()?->id);

        return $this->success(['id' => $type->id, 'is_active' => $type->is_active]);
    }

    private function persistType(SaveShiftTypeRequest $request, SaveShiftTypeAction $save, ?int $shiftTypeId): ShiftType
    {
        $validated = $request->validated();

        try {
            return $save->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new SaveShiftTypeData(
                    $validated['code'],
                    $validated['label'],
                    $validated['start_time'],
                    $validated['end_time'],
                    (int) $validated['break_minutes'],
                    ShiftTypeColor::from($validated['color']),
                    (bool) ($validated['is_active'] ?? true),
                ),
                $request->user()?->id,
                $shiftTypeId,
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            abort(response()->json(['message' => __($e->getMessage())], 422));
        }
    }
}
