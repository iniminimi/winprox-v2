<?php

namespace App\Actions\Time;

use App\Enums\PlannedShiftStatus;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\RosterBlockNormalizer;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Geauditeerde correctie op roosterblokken (pad B) — het enige pad dat
 * historische PlannedShift-records mag wijzigen, verwijderen of aanmaken.
 * Autorisatie zit in PlannedShiftPolicy::correct (tenant-admin); deze actie
 * handhaaft tenant-isolatie, verplichte reden, per-record audit en
 * worker-notificatie ná commit.
 *
 * Eén handle() = één transactie over een lijst ops, zodat een
 * retro-vervanging (delete + create) atomair is.
 */
class CorrectPlannedShiftAction
{
    public function __construct(
        private ListShiftTypesAction $listShiftTypes,
        private AssertPlannedShiftNoOverlapAction $assertNoOverlap,
        private AuditRecorder $audit,
        private NotifyWorkersOfHistoricalRosterCorrectionAction $notifyCorrected,
    ) {}

    /**
     * @param  list<array{type: string, planned_shift_id?: int|null, worker_id?: int|null, block?: array<string, mixed>}>  $ops
     * @return list<array{type: string, planned_shift_id: int, worker_id: int, work_date: string}>
     */
    public function handle(Tenant $tenant, array $ops, string $reason, ?int $actorUserId): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('time.schedule.errors.correction_reason_required');
        }
        if ($ops === []) {
            throw new InvalidArgumentException('time.schedule.errors.correction_empty');
        }

        $types = collect($this->listShiftTypes->handle((int) $tenant->id, false));
        $units = Unit::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('roster_code')
            ->where('roster_code', '!=', '')
            ->get();
        $workers = Worker::query()
            ->with(['team', 'locations'])
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('id');

        $notifyRecords = [];

        $results = DB::transaction(function () use ($tenant, $ops, $reason, $actorUserId, $types, $units, $workers, &$notifyRecords) {
            $results = [];
            $affectedDays = [];

            foreach ($ops as $index => $op) {
                $type = (string) ($op['type'] ?? '');

                if ($type === 'delete') {
                    $shift = $this->lockedShift($tenant, $op);
                    $before = $this->snapshot($shift);
                    $workerId = (int) $shift->worker_id;
                    $date = $shift->work_date->toDateString();
                    $shiftId = (int) $shift->id;
                    $shift->delete();

                    $this->recordAudit($tenant, $actorUserId, 'delete', $shiftId, $workerId, $reason, $before, null);
                    $affectedDays[$workerId.'|'.$date] = [$workerId, $date];
                    $notifyRecords[] = ['worker_id' => $workerId, 'planned_shift_id' => $shiftId, 'date' => $date];
                    $results[] = ['type' => 'delete', 'planned_shift_id' => $shiftId, 'worker_id' => $workerId, 'work_date' => $date];

                    continue;
                }

                if ($type === 'update') {
                    $shift = $this->lockedShift($tenant, $op);
                    $block = $this->requireBlock($op, $index);
                    $worker = $this->requireWorker($workers, (int) $shift->worker_id);
                    $normalized = $this->normalizeFor($block, $types, $units, $worker, $index);
                    $newDate = $this->resolveWorkDate($block, $shift->work_date->toDateString(), $index);

                    $before = $this->snapshot($shift);
                    $shift->fill($normalized);
                    $shift->work_date = $newDate;
                    $shift->save();

                    $after = $this->snapshot($shift->fresh());
                    $this->recordAudit($tenant, $actorUserId, 'update', (int) $shift->id, (int) $shift->worker_id, $reason, $before, $after);
                    $affectedDays[(int) $shift->worker_id.'|'.$newDate] = [(int) $shift->worker_id, $newDate];
                    $notifyRecords[] = ['worker_id' => (int) $shift->worker_id, 'planned_shift_id' => (int) $shift->id, 'date' => $newDate];
                    $results[] = ['type' => 'update', 'planned_shift_id' => (int) $shift->id, 'worker_id' => (int) $shift->worker_id, 'work_date' => $newDate];

                    continue;
                }

                if ($type === 'create') {
                    $block = $this->requireBlock($op, $index);
                    $worker = $this->requireWorker($workers, (int) ($op['worker_id'] ?? 0));
                    $normalized = $this->normalizeFor($block, $types, $units, $worker, $index);
                    $date = $this->resolveWorkDate($block, null, $index);

                    $status = $this->weekPublished($tenant, (int) $worker->id, $date)
                        ? PlannedShiftStatus::Published
                        : PlannedShiftStatus::Draft;

                    $shift = PlannedShift::create([
                        'tenant_id' => $tenant->id,
                        'worker_id' => $worker->id,
                        'work_date' => $date,
                        'status' => $status,
                        ...$normalized,
                    ]);

                    $this->recordAudit($tenant, $actorUserId, 'create', (int) $shift->id, (int) $worker->id, $reason, null, $this->snapshot($shift));
                    $affectedDays[(int) $worker->id.'|'.$date] = [(int) $worker->id, $date];
                    $notifyRecords[] = ['worker_id' => (int) $worker->id, 'planned_shift_id' => (int) $shift->id, 'date' => $date];
                    $results[] = ['type' => 'create', 'planned_shift_id' => (int) $shift->id, 'worker_id' => (int) $worker->id, 'work_date' => $date];

                    continue;
                }

                throw new InvalidArgumentException('time.schedule.errors.correction_unknown_op');
            }

            $intervals = [];
            foreach ($affectedDays as [$workerId, $date]) {
                foreach (PlannedShift::query()->where('worker_id', $workerId)->whereDate('work_date', $date)->get() as $row) {
                    $intervals[] = [
                        'worker_id' => $workerId,
                        'date' => $date,
                        'start' => $row->start_time,
                        'end' => $row->end_time,
                        'kind' => $row->kind->value,
                    ];
                }
            }
            $this->assertNoOverlap->handle($intervals);

            return $results;
        });

        // Pas na een succesvolle commit notificeren — een teruggedraaide
        // transactie mag nooit een melding veroorzaken.
        $this->notifyCorrected->handle($tenant, $notifyRecords);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private function lockedShift(Tenant $tenant, array $op): PlannedShift
    {
        $id = (int) ($op['planned_shift_id'] ?? 0);
        $shift = PlannedShift::query()
            ->whereKey($id)
            ->where('tenant_id', $tenant->id)
            ->lockForUpdate()
            ->first();

        if (! $shift instanceof PlannedShift) {
            throw new RosterValidationException('time.schedule.errors.unknown_block');
        }

        return $shift;
    }

    /**
     * @param  array<string, mixed>  $op
     * @return array<string, mixed>
     */
    private function requireBlock(array $op, int $index): array
    {
        $block = $op['block'] ?? null;
        if (! is_array($block)) {
            throw new RosterValidationException(
                'time.schedule.errors.correction_missing_block',
                cells: [['index' => $index, 'error' => 'time.schedule.errors.correction_missing_block']],
            );
        }

        return $block;
    }

    /**
     * @param  Collection<int, Worker>  $workers
     */
    private function requireWorker(Collection $workers, int $workerId): Worker
    {
        $worker = $workers->get($workerId);
        if (! $worker instanceof Worker) {
            throw new RosterValidationException('time.schedule.errors.unknown_worker');
        }

        return $worker;
    }

    /**
     * @param  Collection<int, \App\Models\ShiftType>  $types
     * @param  Collection<int, Unit>  $units
     * @return array{shift_type_id: ?int, kind: string, unit_id: ?int, unit_code: ?string, unit_name: ?string, location_id: ?int, start_time: ?string, end_time: ?string, break_minutes: int, description: ?string}
     */
    private function normalizeFor(array $block, Collection $types, Collection $units, Worker $worker, int $index): array
    {
        $catalog = $units->filter(
            fn (Unit $unit) => $worker->canClockAt((int) $unit->location_id),
        );

        return RosterBlockNormalizer::normalize($block, $types, $catalog, $index);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function resolveWorkDate(array $block, ?string $fallback, int $index): string
    {
        $raw = $block['work_date'] ?? $fallback;
        $date = is_string($raw) ? Carbon::createFromFormat('Y-m-d', $raw) : null;
        if ($date === false || $date === null || $date->toDateString() !== $raw) {
            throw new RosterValidationException(
                'time.schedule.errors.correction_invalid_date',
                cells: [['index' => $index, 'error' => 'time.schedule.errors.correction_invalid_date']],
            );
        }

        return $date->toDateString();
    }

    private function weekPublished(Tenant $tenant, int $workerId, string $date): bool
    {
        return PlannedShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('worker_id', $workerId)
            ->whereBetween('work_date', [
                Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString(),
                Carbon::parse($date)->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ])
            ->where('status', PlannedShiftStatus::Published)
            ->exists();
    }

    /**
     * @return array{planned_shift_id: int, worker_id: int, work_date: string, shift_type_id: ?int, kind: string, unit_id: ?int, unit_code: ?string, unit_name: ?string, location_id: ?int, start_time: ?string, end_time: ?string, break_minutes: int, description: ?string, status: string}
     */
    private function snapshot(PlannedShift $shift): array
    {
        return [
            'planned_shift_id' => (int) $shift->id,
            'worker_id' => (int) $shift->worker_id,
            'work_date' => $shift->work_date->toDateString(),
            'shift_type_id' => $shift->shift_type_id !== null ? (int) $shift->shift_type_id : null,
            'kind' => $shift->kind->value,
            'unit_id' => $shift->unit_id !== null ? (int) $shift->unit_id : null,
            'unit_code' => $shift->unit_code,
            'unit_name' => $shift->unit_name,
            'location_id' => $shift->location_id !== null ? (int) $shift->location_id : null,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'break_minutes' => (int) $shift->break_minutes,
            'description' => $shift->description,
            'status' => $shift->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function recordAudit(
        Tenant $tenant,
        ?int $actorUserId,
        string $operation,
        int $shiftId,
        int $workerId,
        string $reason,
        ?array $before,
        ?array $after,
    ): void {
        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'planned_shift.corrected',
            modelType: PlannedShift::class,
            modelId: $shiftId,
            payload: [
                'operation' => $operation,
                'planned_shift_id' => $shiftId,
                'worker_id' => $workerId,
                'reason' => $reason,
                'before' => $before,
                'after' => $after,
            ],
        );
    }
}
