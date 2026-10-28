<?php

namespace App\Actions\Time;

use App\Data\Time\SavePlannedDayData;
use App\Enums\PlannedShiftStatus;
use App\Enums\RosterRestorePointSource;
use App\Events\Time\ScheduleSaved;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Time\RosterBlockNormalizer;
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
        private ListShiftTypesAction $listShiftTypes,
        private RecordRosterRestorePointAction $recordRestorePoint,
    ) {}

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return array{created: int, updated: int, deleted: int}
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
                ->with('shiftType')
                ->where('worker_id', $worker->id)
                ->whereDate('work_date', $date)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $before = $this->recordRestorePoint->capture($existing);

            $payloadIds = collect($data->blocks)
                ->pluck('id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($payloadIds->diff($existing->keys()->map(fn ($id) => (int) $id))->isNotEmpty()) {
                throw new RosterValidationException('time.schedule.errors.unknown_block');
            }

            // Gewijzigde of nieuwe blokken worden draft, ook op een al
            // gepubliceerde dag: pas Publiceren maakt ze zichtbaar voor de
            // uitvoerder en toont "Niet gepubliceerd" tot dan in de toolbar.
            $status = PlannedShiftStatus::Draft;

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
                        $row->status = $status;
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

            if ($created + $updated + (int) $deleted > 0) {
                $this->recordRestorePoint->handle(
                    $tenant,
                    $actorUserId,
                    RosterRestorePointSource::Day,
                    [['worker_id' => (int) $worker->id, 'date' => $date]],
                    $before,
                );
            }

            $afterCount = PlannedShift::query()
                ->where('worker_id', $worker->id)
                ->whereDate('work_date', $date)
                ->count();

            event(new ScheduleSaved(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                weekStart: $date,
                workerIds: [(int) $worker->id],
                dates: [$date],
                count: $afterCount,
            ));

            return [
                'created' => $created,
                'updated' => $updated,
                'deleted' => (int) $deleted,
            ];
        });
    }
}
