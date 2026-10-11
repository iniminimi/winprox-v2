<?php

namespace App\Actions\Time;

use App\Data\Time\SavePlannedDayData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Events\Time\ScheduleSaved;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Time\RosterBlockNormalizer;
use App\Support\Time\RosterDaySignature;
use App\Support\Time\RosterHistoryLock;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
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
        private ListWorkerUnavailableDatesAction $listUnavailableDates,
    ) {}

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return array{created: int, updated: int, deleted: int, warnings: list<array{worker_id: int, worker: string, date: string}>}
     */
    public function handle(Tenant $tenant, SavePlannedDayData $data, ?int $actorUserId, ?array $actorLocationIds = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $date = Carbon::parse($data->date)->toDateString();
        RosterHistoryLock::assertWritableDate($date);

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
            $normalized[] = RosterBlockNormalizer::normalize($block, $types, $catalog, $index);
        }

        // Zachte waarschuwing: werkblok op een structureel onbeschikbare
        // weekdag blokkeert de save niet maar wordt gerapporteerd.
        $unavailable = $this->listUnavailableDates->handle(
            (int) $tenant->id,
            [(int) $worker->id],
            $date,
            $date,
        );
        $warnings = [];
        if (isset($unavailable[(int) $worker->id][$date])
            && collect($normalized)->contains(fn (array $block) => $block['kind'] === ShiftTypeKind::Work->value)) {
            $warnings[] = [
                'worker_id' => (int) $worker->id,
                'worker' => $worker->displayName(),
                'date' => $date,
            ];
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

        return DB::transaction(function () use ($tenant, $worker, $date, $normalized, $data, $actorUserId, $warnings) {
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
                'warnings' => $warnings,
            ];
        });
    }

}
