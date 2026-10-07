<?php

namespace App\Actions\Time;

use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Unit;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Data voor de day-editor van één worker×dag: bestaande blokken,
 * types, toegestane units en ongeplande (read-only) klok-sessies.
 */
class LoadRosterDayEditorAction
{
    public function __construct(private ListShiftTypesAction $listShiftTypes) {}

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return array{worker: array<string, mixed>, date: string, blocks: list<array<string, mixed>>, unplanned_sessions: list<array<string, mixed>>, types: list<array<string, mixed>>, units: list<array<string, mixed>>}
     */
    public function handle(int $tenantId, int $workerId, string $date, ?array $actorLocationIds = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $worker = Worker::query()
            ->with(['team', 'locations'])
            ->where('tenant_id', $tenantId)
            ->where('id', $workerId)
            ->first();
        if (! $worker instanceof Worker) {
            throw new InvalidArgumentException('time.schedule.errors.unknown_worker');
        }

        $date = Carbon::parse($date)->toDateString();

        $blocks = PlannedShift::query()
            ->with('shiftType')
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->whereDate('work_date', $date)
            ->orderByRaw('COALESCE(start_time, "00:00:00")')
            ->orderBy('id')
            ->get()
            ->map(fn (PlannedShift $shift) => [
                'id' => (int) $shift->id,
                'shift_type_id' => $shift->shift_type_id !== null ? (int) $shift->shift_type_id : null,
                'kind' => $shift->kind->value,
                'status' => $shift->status->value,
                'start_time' => ShiftType::formatTime($shift->start_time),
                'end_time' => ShiftType::formatTime($shift->end_time),
                'break_minutes' => (int) $shift->break_minutes,
                'unit_id' => $shift->unit_id !== null ? (int) $shift->unit_id : null,
                'description' => $shift->description,
                'display' => $shift->displayValue(),
            ])
            ->values()
            ->all();

        $blockWindows = collect($blocks)
            ->filter(fn (array $block) => $block['kind'] === 'work' && $block['start_time'] !== '' && $block['end_time'] !== '')
            ->map(fn (array $block) => [
                ShiftType::timeToMinutes($block['start_time']),
                ShiftType::timeToMinutes($block['end_time']),
            ])
            ->all();

        $dayStart = Carbon::parse($date)->startOfDay();
        $unplannedSessions = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('clock_in_at', '>=', $dayStart)
            ->where('clock_in_at', '<=', $dayStart->copy()->endOfDay())
            ->orderBy('clock_in_at')
            ->get()
            ->filter(function (WorkShift $punch) use ($blockWindows) {
                $in = ShiftType::timeToMinutes($punch->clock_in_at->format('H:i'));
                $out = $punch->clock_out_at === null
                    ? 24 * 60
                    : ShiftType::timeToMinutes($punch->clock_out_at->format('H:i'));
                foreach ($blockWindows as [$start, $end]) {
                    if ($in < $end && $start < $out) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn (WorkShift $punch) => [
                'in' => $punch->clock_in_at->format('H:i'),
                'out' => $punch->clock_out_at?->format('H:i'),
            ])
            ->values()
            ->all();

        $types = collect($this->listShiftTypes->handle($tenantId, false))
            ->filter(fn (ShiftType $type) => $type->is_active)
            ->map(fn (ShiftType $type) => [
                'id' => (int) $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'kind' => $type->kind->value,
                'start' => ShiftType::formatTime($type->start_time),
                'end' => ShiftType::formatTime($type->end_time),
            ])
            ->values()
            ->all();

        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('roster_code')
            ->where('roster_code', '!=', '')
            ->when($actorLocationIds !== null, fn ($q) => $q->whereIn('location_id', $actorLocationIds ?: [0]))
            ->orderBy('roster_code')
            ->get()
            ->filter(fn (Unit $unit) => $worker->canClockAt((int) $unit->location_id))
            ->map(fn (Unit $unit) => [
                'id' => (int) $unit->id,
                'code' => Unit::normalizeRosterCode((string) $unit->roster_code),
                'name' => $unit->name,
            ])
            ->values()
            ->all();

        return [
            'worker' => [
                'id' => (int) $worker->id,
                'name' => $worker->displayName(),
            ],
            'date' => $date,
            'blocks' => $blocks,
            'unplanned_sessions' => $unplannedSessions,
            'types' => $types,
            'units' => $units,
        ];
    }
}
