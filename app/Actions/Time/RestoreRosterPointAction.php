<?php

namespace App\Actions\Time;

use App\Data\Time\RosterRestoreAssessment;
use App\Enums\RosterRestorePointSource;
use App\Exceptions\RosterRestoreConflictException;
use App\Models\PlannedShift;
use App\Models\RosterRestorePoint;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Zet één herstelpunt terug op het bereik dat het punt bewaart.
 * Vlak vóór de wijziging wordt die stand zelf een herstelpunt,
 * zodat het terugzetten opnieuw kan worden teruggedraaid.
 * Een vergrendelde dag, een id op een andere dag of goedgekeurd verlof
 * blokkeert het herstel. Een afwijkend rooster wordt niet stil overschreven:
 * dat vraagt een expliciete bevestiging van exact de getoonde stand.
 * Wijkt die stand af onder de vergrendeling, dan wordt niets geschreven.
 */
class RestoreRosterPointAction
{
    public function __construct(
        private AssertPlannedShiftNoOverlapAction $assertNoOverlap,
        private RecordRosterRestorePointAction $recordRestorePoint,
        private AssessRosterRestorePointAction $assess,
    ) {}

    /**
     * Zelfde beoordeling als bij het herstel, zonder te schrijven.
     *
     * @return array{
     *     assessment: RosterRestoreAssessment,
     *     current: list<array<string, mixed>>,
     *     saved: list<array<string, mixed>>
     * }
     */
    public function preview(Tenant $tenant, User $actor, RosterRestorePoint $point): array
    {
        $this->assertCan($tenant, $actor, $point);
        [$scope, $scopeKeys, $workerIds, $dates, $savedInScope] = $this->scopeParts($point);
        $current = $this->candidates($tenant, $workerIds, $dates, $savedInScope, false);

        return [
            'assessment' => $this->assess->handle((int) $tenant->id, $scope, $savedInScope, $current),
            'current' => $this->rowsInScope($tenant, $scopeKeys, $current),
            'saved' => $savedInScope,
        ];
    }

    public function handle(Tenant $tenant, User $actor, RosterRestorePoint $point, bool $confirmed = false, ?string $seenToken = null): ?RosterRestorePoint
    {
        $this->assertCan($tenant, $actor, $point);
        [$scope, $scopeKeys, $workerIds, $dates, $savedInScope] = $this->scopeParts($point);

        return DB::transaction(function () use ($tenant, $actor, $scope, $scopeKeys, $workerIds, $dates, $savedInScope, $confirmed, $seenToken): ?RosterRestorePoint {
            $locked = $this->candidates($tenant, $workerIds, $dates, $savedInScope, true);

            $assessment = $this->assess->handle((int) $tenant->id, $scope, $savedInScope, $locked);
            if ($assessment->blocked()) {
                throw new RosterRestoreConflictException($this->blockMessage($assessment), $assessment);
            }
            if ($assessment->needsConfirmation() && ! $confirmed) {
                throw new RosterRestoreConflictException(
                    'time.schedule.errors.restore_confirmation_required',
                    $assessment,
                );
            }
            if ($assessment->differences === []) {
                return null;
            }
            if ($seenToken !== $assessment->token()) {
                throw new RosterRestoreConflictException(
                    'time.schedule.errors.restore_stale',
                    $assessment,
                );
            }

            $savedIds = array_map(fn (array $row): int => (int) $row['planned_shift_id'], $savedInScope);
            $byId = $locked->keyBy(fn (PlannedShift $shift): int => (int) $shift->id);

            $before = $locked->filter(function (PlannedShift $shift) use ($tenant, $scopeKeys): bool {
                if ((int) $shift->tenant_id !== (int) $tenant->id) {
                    return false;
                }
                $key = ((int) $shift->worker_id).':'.$shift->work_date->toDateString();

                return isset($scopeKeys[$key]);
            });
            $undo = $this->recordRestorePoint->handle(
                $tenant,
                $actor->id,
                RosterRestorePointSource::Restore,
                $scope,
                $this->recordRestorePoint->capture($before),
            );

            $keepIds = array_fill_keys($savedIds, true);
            $deleteIds = [];
            foreach ($locked as $shift) {
                if ((int) $shift->tenant_id !== (int) $tenant->id) {
                    continue;
                }
                $key = ((int) $shift->worker_id).':'.$shift->work_date->toDateString();
                if (! isset($scopeKeys[$key]) || isset($keepIds[(int) $shift->id])) {
                    continue;
                }
                $deleteIds[] = (int) $shift->id;
            }

            if ($deleteIds !== []) {
                PlannedShift::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('id', $deleteIds)
                    ->delete();
            }

            foreach ($savedInScope as $row) {
                $attributes = $this->attributes($tenant, $row);
                $found = $byId->get((int) $row['planned_shift_id']);
                if ($found instanceof PlannedShift) {
                    $found->forceFill($attributes)->save();

                    continue;
                }

                $created = new PlannedShift;
                $created->forceFill($attributes);
                $created->id = (int) $row['planned_shift_id'];
                $created->save();
            }

            $intervals = [];
            foreach ($savedInScope as $row) {
                $intervals[] = [
                    'worker_id' => (int) $row['worker_id'],
                    'date' => (string) $row['work_date'],
                    'start' => $row['start_time'] ?? null,
                    'end' => $row['end_time'] ?? null,
                    'kind' => (string) $row['kind'],
                ];
            }
            $this->assertNoOverlap->handle($intervals);

            return $undo;
        });
    }

    private function assertCan(Tenant $tenant, User $actor, RosterRestorePoint $point): void
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $point->tenant_id !== (int) $tenant->id
            || ! SuperuserTenantAccess::canAccessTenant($actor, (int) $tenant->id)
            || ! Gate::forUser($actor)->allows('update', PlannedShift::class)
        ) {
            throw new AuthorizationException;
        }
    }

    /**
     * @return array{0: list<array{worker_id: int, date: string}>, 1: array<string, true>, 2: array<int, int>, 3: array<string, string>, 4: list<array<string, mixed>>}
     */
    private function scopeParts(RosterRestorePoint $point): array
    {
        /** @var list<array{worker_id: int, date: string}> $scope */
        $scope = is_array($point->scope) ? $point->scope : [];
        /** @var list<array<string, mixed>> $saved */
        $saved = is_array($point->shifts) ? $point->shifts : [];

        $scopeKeys = [];
        $workerIds = [];
        $dates = [];
        foreach ($scope as $item) {
            $workerId = (int) $item['worker_id'];
            $date = (string) $item['date'];
            $scopeKeys[$workerId.':'.$date] = true;
            $workerIds[$workerId] = $workerId;
            $dates[$date] = $date;
        }

        $savedInScope = [];
        foreach ($saved as $row) {
            $key = ((int) $row['worker_id']).':'.(string) $row['work_date'];
            if (isset($scopeKeys[$key])) {
                $savedInScope[] = $row;
            }
        }

        return [$scope, $scopeKeys, $workerIds, $dates, $savedInScope];
    }

    /**
     * @param  array<int, int>  $workerIds
     * @param  array<string, string>  $dates
     * @param  list<array<string, mixed>>  $savedInScope
     * @return Collection<int, PlannedShift>
     */
    private function candidates(Tenant $tenant, array $workerIds, array $dates, array $savedInScope, bool $lock): Collection
    {
        $savedIds = array_map(fn (array $row): int => (int) $row['planned_shift_id'], $savedInScope);

        $query = PlannedShift::query()
            ->with('shiftType')
            ->withoutGlobalScope('tenant')
            ->where(function ($inner) use ($tenant, $workerIds, $dates, $savedIds): void {
                $inner->where(function ($scope) use ($tenant, $workerIds, $dates): void {
                    $scope->where('tenant_id', $tenant->id)
                        ->whereIn('worker_id', array_values($workerIds) ?: [0])
                        ->where(function ($byDate) use ($dates): void {
                            foreach (array_values($dates) as $date) {
                                $byDate->orWhereDate('work_date', $date);
                            }
                        });
                });
                if ($savedIds !== []) {
                    $inner->orWhereIn('id', $savedIds);
                }
            })
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * @param  array<string, true>  $scopeKeys
     * @param  Collection<int, PlannedShift>  $shifts
     * @return list<array<string, mixed>>
     */
    private function rowsInScope(Tenant $tenant, array $scopeKeys, Collection $shifts): array
    {
        $inScope = $shifts->filter(function (PlannedShift $shift) use ($tenant, $scopeKeys): bool {
            if ((int) $shift->tenant_id !== (int) $tenant->id) {
                return false;
            }
            $key = ((int) $shift->worker_id).':'.$shift->work_date->toDateString();

            return isset($scopeKeys[$key]);
        });

        return $this->recordRestorePoint->capture($inScope);
    }

    private function blockMessage(RosterRestoreAssessment $assessment): string
    {
        $codes = array_values(array_unique(array_column($assessment->blocks, 'code')));
        if ($codes === ['locked_day']) {
            return 'time.schedule.errors.locked_day';
        }
        if ($codes === ['restore_id_elsewhere']) {
            return 'time.schedule.errors.restore_id_elsewhere';
        }
        if ($codes === ['approved_absence']) {
            return 'time.schedule.errors.restore_approved_absence';
        }

        return 'time.schedule.errors.restore_blocked';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributes(Tenant $tenant, array $row): array
    {
        return [
            'tenant_id' => (int) $tenant->id,
            'worker_id' => (int) $row['worker_id'],
            'work_date' => (string) $row['work_date'],
            'shift_type_id' => $row['shift_type_id'] ?? null,
            'kind' => (string) $row['kind'],
            'unit_id' => $row['unit_id'] ?? null,
            'unit_code' => $row['unit_code'] ?? null,
            'unit_name' => $row['unit_name'] ?? null,
            'location_id' => $row['location_id'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'break_minutes' => (int) ($row['break_minutes'] ?? 0),
            'description' => $row['description'] ?? null,
            'status' => (string) $row['status'],
        ];
    }
}
