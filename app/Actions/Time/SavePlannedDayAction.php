<?php

namespace App\Actions\Time;

use App\Data\Time\SavePlannedDayData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Events\Time\ScheduleSaved;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Time\RosterDaySignature;
use App\Support\Time\TimeModuleAccess;
use App\Support\Validation\TextDescriptionLimits;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Id-aware sync van één worker×dag vanuit de day-editor.
 * Bestaande blokken worden bijgewerkt op id; toegevoegde blokken aangemaakt;
 * weggelaten bestaande ids verwijderd. De dag wordt nooit als geheel
 * verwijderd en opnieuw aangemaakt — notities/attendance blijven aan het
 * blok hangen.
 */
class SavePlannedDayAction
{
    public function __construct(
        private AssertPlannedShiftNoOverlapAction $assertNoOverlap,
        private NotifyWorkersRosterChangedAction $notifyChanged,
        private ListShiftTypesAction $listShiftTypes,
    ) {}

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return array{created: int, updated: int, deleted: int, warnings: list<string>}
     */
    public function handle(Tenant $tenant, SavePlannedDayData $data, ?int $actorUserId, ?array $actorLocationIds = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $date = Carbon::parse($data->date)->toDateString();

        $worker = Worker::query()
            ->with(['team', 'locations'])
            ->where('tenant_id', $tenant->id)
            ->where('id', $data->workerId)
            ->first();
        if (! $worker instanceof Worker) {
            throw new RosterValidationException('time.schedule.errors.unknown_worker');
        }

        $types = collect($this->listShiftTypes->handle((int) $tenant->id, false));
        $units = Unit::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('roster_code')
            ->where('roster_code', '!=', '')
            ->when($actorLocationIds !== null, fn ($q) => $q->whereIn('location_id', $actorLocationIds ?: [0]))
            ->get();

        $catalog = $units->filter(
            fn (Unit $unit) => $worker->canClockAt((int) $unit->location_id),
        );

        $normalized = [];
        foreach ($data->blocks as $index => $block) {
            $normalized[] = $this->normalizeBlock($block, $types, $catalog, $index);
        }

        $this->assertNoOverlap->handle(array_map(
            fn (array $block) => [
                'worker_id' => (int) $worker->id,
                'date' => $date,
                'start' => $block['start_time'],
                'end' => $block['end_time'],
                'kind' => $block['kind'],
            ],
            $normalized,
        ));

        return DB::transaction(function () use ($tenant, $worker, $date, $normalized, $data, $actorUserId) {
            $existing = PlannedShift::query()
                ->where('worker_id', $worker->id)
                ->whereDate('work_date', $date)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $payloadIds = collect($data->blocks)
                ->pluck('id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($payloadIds->diff($existing->keys()->map(fn ($id) => (int) $id))->isNotEmpty()) {
                throw new RosterValidationException('time.schedule.errors.unknown_block');
            }

            $dayPublished = $existing->contains(
                fn (PlannedShift $shift) => $shift->status->isPublished(),
            ) || PlannedShift::query()
                ->where('tenant_id', $tenant->id)
                ->where('worker_id', $worker->id)
                ->whereBetween('work_date', [
                    Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString(),
                    Carbon::parse($date)->endOfWeek(Carbon::SUNDAY)->toDateString(),
                ])
                ->where('status', PlannedShiftStatus::Published)
                ->exists();
            $status = $dayPublished ? PlannedShiftStatus::Published : PlannedShiftStatus::Draft;

            $beforeSignature = RosterDaySignature::of($existing->values());

            $created = 0;
            $updated = 0;
            $cursor = 0;
            $keptIds = [];

            foreach ($normalized as $block) {
                $id = $data->blocks[$cursor]['id'] ?? null;
                $cursor++;
                $id = $id !== null && $id !== '' ? (int) $id : null;

                if ($id !== null) {
                    /** @var PlannedShift $row */
                    $row = $existing->get($id);
                    $row->fill([
                        'shift_type_id' => $block['shift_type_id'],
                        'kind' => $block['kind'],
                        'unit_id' => $block['unit_id'],
                        'unit_code' => $block['unit_code'],
                        'unit_name' => $block['unit_name'],
                        'location_id' => $block['location_id'],
                        'start_time' => $block['start_time'],
                        'end_time' => $block['end_time'],
                        'break_minutes' => $block['break_minutes'],
                        'description' => $block['description'],
                    ]);
                    if ($row->isDirty()) {
                        $row->save();
                        $updated++;
                    }
                    $keptIds[] = $id;

                    continue;
                }

                PlannedShift::create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $worker->id,
                    'work_date' => $date,
                    'shift_type_id' => $block['shift_type_id'],
                    'kind' => $block['kind'],
                    'unit_id' => $block['unit_id'],
                    'unit_code' => $block['unit_code'],
                    'unit_name' => $block['unit_name'],
                    'location_id' => $block['location_id'],
                    'start_time' => $block['start_time'],
                    'end_time' => $block['end_time'],
                    'break_minutes' => $block['break_minutes'],
                    'description' => $block['description'],
                    'status' => $status,
                ]);
                $created++;
            }

            $deleted = PlannedShift::query()
                ->whereIn('id', $existing->keys()->diff($keptIds)->all() ?: [0])
                ->delete();

            $after = PlannedShift::query()
                ->where('worker_id', $worker->id)
                ->whereDate('work_date', $date)
                ->get();
            $changed = $beforeSignature !== RosterDaySignature::of($after);

            if ($changed && $status === PlannedShiftStatus::Published) {
                $this->notifyChanged->handle($tenant, [[
                    'worker_id' => (int) $worker->id,
                    'date' => $date,
                ]]);
            }

            event(new ScheduleSaved(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                weekStart: $date,
                workerIds: [(int) $worker->id],
                dates: [$date],
                count: $after->count(),
            ));

            return [
                'created' => $created,
                'updated' => $updated,
                'deleted' => (int) $deleted,
                'warnings' => [],
            ];
        });
    }

    /**
     * @param  array{id?: int|null, shift_type_id?: int|null, start_time?: ?string, end_time?: ?string, break_minutes?: int|null, unit_id?: int|null, description?: ?string}  $block
     * @param  Collection<int, ShiftType>  $types
     * @param  Collection<int, Unit>  $units
     * @return array{shift_type_id: ?int, kind: string, unit_id: ?int, unit_code: ?string, unit_name: ?string, location_id: ?int, start_time: ?string, end_time: ?string, break_minutes: int, description: ?string}
     */
    private function normalizeBlock(array $block, Collection $types, Collection $units, int $index): array
    {
        $typeId = $block['shift_type_id'] ?? null;
        $typeId = $typeId !== null && $typeId !== '' ? (int) $typeId : null;

        $resolved = [
            'shift_type_id' => null,
            'kind' => ShiftTypeKind::Work->value,
            'start_time' => null,
            'end_time' => null,
            'break_minutes' => (int) ($block['break_minutes'] ?? 0),
            'description' => trim((string) ($block['description'] ?? '')) !== ''
                ? mb_substr(trim((string) $block['description']), 0, TextDescriptionLimits::MAX)
                : null,
        ];

        if ($typeId !== null) {
            /** @var ?ShiftType $type */
            $type = $types->first(fn (ShiftType $shiftType) => (int) $shiftType->id === $typeId);
            if (! $type instanceof ShiftType || ! $type->is_active) {
                throw new RosterValidationException(
                    'time.schedule.errors.unknown_code',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.unknown_code']],
                );
            }
            $resolved['shift_type_id'] = $typeId;
            $resolved['kind'] = $type->kind->value;
            if ($type->kind->isWork()) {
                $resolved['start_time'] = ShiftType::formatTime($type->start_time);
                $resolved['end_time'] = ShiftType::formatTime($type->end_time);
                $resolved['break_minutes'] = (int) $type->break_minutes;
            }
        } else {
            $start = trim((string) ($block['start_time'] ?? ''));
            $end = trim((string) ($block['end_time'] ?? ''));
            if (
                ! preg_match('/^\d{2}:\d{2}$/', $start)
                || ! preg_match('/^\d{2}:\d{2}$/', $end)
            ) {
                throw new RosterValidationException(
                    'time.schedule.errors.invalid_time',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.invalid_time']],
                );
            }
            if (ShiftType::timeToMinutes($end) <= ShiftType::timeToMinutes($start)) {
                throw new RosterValidationException(
                    'time.schedule.errors.night_not_allowed',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.night_not_allowed']],
                );
            }
            $resolved['start_time'] = $start;
            $resolved['end_time'] = $end;
        }

        $unitId = $block['unit_id'] ?? null;
        $unitId = $unitId !== null && $unitId !== '' ? (int) $unitId : null;

        if ($resolved['kind'] !== ShiftTypeKind::Work->value && $unitId !== null) {
            throw new RosterValidationException(
                'time.schedule.errors.absence_has_unit',
                cells: [['index' => $index, 'error' => 'time.schedule.errors.absence_has_unit']],
            );
        }

        $resolved['unit_id'] = null;
        $resolved['unit_code'] = null;
        $resolved['unit_name'] = null;
        $resolved['location_id'] = null;

        if ($unitId !== null) {
            /** @var ?Unit $unit */
            $unit = $units->first(fn (Unit $candidate) => (int) $candidate->id === $unitId);
            if (! $unit instanceof Unit) {
                throw new RosterValidationException(
                    'time.schedule.errors.unknown_unit',
                    cells: [['index' => $index, 'error' => 'time.schedule.errors.unknown_unit']],
                );
            }
            $resolved['unit_id'] = $unitId;
            $resolved['unit_code'] = Unit::normalizeRosterCode((string) $unit->roster_code);
            $resolved['unit_name'] = (string) $unit->name;
            $resolved['location_id'] = (int) $unit->location_id;
        }

        return $resolved;
    }

}
