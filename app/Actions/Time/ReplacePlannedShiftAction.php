<?php

namespace App\Actions\Time;

use App\Enums\PlannedShiftStatus;
use App\Events\Time\ScheduleSaved;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;

/**
 * Directe vervanging zonder afwezigheidsaanvraag ("ziek vanochtend").
 * De originele blokken worden op dezelfde rij omgezet naar een afwezigheidstype
 * (identiteit behouden → geen `missing` in de attendance, unit-snapshot leeg).
 * De vervanger krijgt per blok een nieuw werkblok met hetzelfde tijdslot,
 * dezelfde locatie en dezelfde unit. Geen link-kolom in v1.
 */
class ReplacePlannedShiftAction
{
    public function __construct(
        private SuggestReplacementAction $suggest,
        private AssertPlannedShiftNoOverlapAction $assertNoOverlap,
        private NotifyWorkersRosterChangedAction $notifyChanged,
    ) {}

    /**
     * @param  list<int>  $plannedShiftIds  werkblokken van dezelfde worker×dag
     * @return array{converted: int, created: list<PlannedShift>}
     */
    public function handle(
        Tenant $tenant,
        array $plannedShiftIds,
        int $replacementWorkerId,
        int $absenceShiftTypeId,
        ?int $actorUserId = null,
    ): array {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $absenceType = ShiftType::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $absenceShiftTypeId)
            ->where('is_active', true)
            ->first();
        if (! $absenceType instanceof ShiftType || ! $absenceType->kind->isAbsence()) {
            throw new RosterValidationException('time.schedule.errors.replacement_no_absence_type');
        }

        $replacement = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $replacementWorkerId)
            ->where('is_active', true)
            ->first();
        if (! $replacement instanceof Worker) {
            throw new RosterValidationException('time.schedule.errors.unknown_worker');
        }

        // Server-side hercontrole: de vervanger moet in de kandidatenlijst staan.
        $eligible = collect($this->suggest->handle($tenant, $plannedShiftIds))
            ->pluck('worker_id')
            ->contains($replacementWorkerId);
        if (! $eligible) {
            throw new RosterValidationException('time.schedule.errors.replacement_not_eligible');
        }

        return DB::transaction(function () use ($tenant, $plannedShiftIds, $replacement, $absenceType, $actorUserId) {
            $blocks = PlannedShift::query()
                ->with('shiftType')
                ->where('tenant_id', $tenant->id)
                ->whereIn('id', $plannedShiftIds)
                ->lockForUpdate()
                ->get();

            /** @var PlannedShift $first */
            $first = $blocks->first();
            $workerId = (int) $first->worker_id;
            $date = $first->work_date->toDateString();
            $published = $blocks->contains(
                fn (PlannedShift $shift) => $shift->status->isPublished(),
            );

            // Overlap-guard voor de vervanger: bestaande werkdag + nieuwe blokken.
            $intervals = PlannedShift::query()
                ->where('worker_id', $replacement->id)
                ->whereDate('work_date', $date)
                ->get(['start_time', 'end_time', 'kind'])
                ->map(fn (PlannedShift $shift) => [
                    'worker_id' => (int) $replacement->id,
                    'date' => $date,
                    'start' => $shift->start_time,
                    'end' => $shift->end_time,
                    'kind' => $shift->kind->value,
                ])
                ->all();
            foreach ($blocks as $block) {
                $intervals[] = [
                    'worker_id' => (int) $replacement->id,
                    'date' => $date,
                    'start' => $block->start_time,
                    'end' => $block->end_time,
                    'kind' => $block->kind->value,
                ];
            }
            $this->assertNoOverlap->handle($intervals);

            $created = [];
            foreach ($blocks as $block) {
                $created[] = PlannedShift::create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $replacement->id,
                    'work_date' => $date,
                    'shift_type_id' => $block->shift_type_id,
                    'kind' => $block->kind,
                    'unit_id' => $block->unit_id,
                    'unit_code' => $block->unit_code,
                    'unit_name' => $block->unit_name,
                    'location_id' => $block->location_id,
                    'start_time' => $block->start_time,
                    'end_time' => $block->end_time,
                    'break_minutes' => $block->break_minutes,
                    'description' => $block->description,
                    'status' => $block->status,
                ]);

                $block->kind = $absenceType->kind;
                $block->shift_type_id = $absenceType->id;
                $block->unit_id = null;
                $block->unit_code = null;
                $block->unit_name = null;
                $block->save();
            }

            if ($published && $date >= now()->toDateString()) {
                $this->notifyChanged->handle($tenant, [
                    ['worker_id' => $workerId, 'date' => $date],
                    ['worker_id' => (int) $replacement->id, 'date' => $date],
                ]);
            }

            event(new ScheduleSaved(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                weekStart: $date,
                workerIds: [$workerId, (int) $replacement->id],
                dates: [$date],
                count: count($created),
            ));

            return ['converted' => $blocks->count(), 'created' => $created];
        });
    }
}
