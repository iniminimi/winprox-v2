<?php

namespace App\Actions\Time;

use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Support\Time\TimeModuleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ApplyAbsenceDaysToRosterAction
{
    /**
     * @return list<array{
     *     date: string,
     *     planned_shift_id: int,
     *     kind: string,
     *     code: ?string,
     *     label: ?string,
     *     start: ?string,
     *     end: ?string,
     *     unit_code: ?string,
     *     unit_name: ?string,
     *     status: string
     * }>
     */
    public function handle(Tenant $tenant, AbsenceRequest $request): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $shiftType = $request->shiftType;
        if (! $shiftType instanceof ShiftType) {
            throw new \InvalidArgumentException('no_shift_type');
        }

        $kind = $request->kind;
        if (! $kind instanceof ShiftTypeKind || ! $kind->isRequestableAbsence()) {
            throw new \InvalidArgumentException('invalid_kind');
        }

        $from = CarbonImmutable::parse($request->date_from->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($request->date_to->toDateString())->startOfDay();

        return DB::transaction(function () use ($tenant, $request, $shiftType, $kind, $from, $to) {
            $existing = PlannedShift::query()
                ->with('shiftType')
                ->where('tenant_id', $tenant->id)
                ->where('worker_id', $request->worker_id)
                ->whereDate('work_date', '>=', $from->toDateString())
                ->whereDate('work_date', '<=', $to->toDateString())
                ->orderBy('work_date')
                ->orderBy('id')
                ->get();

            $snapshot = [];
            foreach ($existing as $shift) {
                $snapshot[] = [
                    'date' => $shift->work_date->toDateString(),
                    'planned_shift_id' => (int) $shift->id,
                    'kind' => $shift->kind->value,
                    'code' => $shift->shiftType?->code,
                    'label' => $shift->shiftType?->label,
                    'start' => $shift->start_time,
                    'end' => $shift->end_time,
                    'unit_code' => $shift->unit_code,
                    'unit_name' => $shift->unit_name,
                    'status' => $shift->status->value,
                ];
            }

            PlannedShift::query()
                ->where('tenant_id', $tenant->id)
                ->where('worker_id', $request->worker_id)
                ->whereDate('work_date', '>=', $from->toDateString())
                ->whereDate('work_date', '<=', $to->toDateString())
                ->delete();

            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                PlannedShift::query()->create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $request->worker_id,
                    'work_date' => $day->toDateString(),
                    'shift_type_id' => $shiftType->id,
                    'kind' => $kind,
                    'unit_id' => null,
                    'unit_code' => null,
                    'unit_name' => null,
                    'location_id' => null,
                    'start_time' => null,
                    'end_time' => null,
                    'break_minutes' => 0,
                    'status' => PlannedShiftStatus::Published,
                ]);
            }

            return $snapshot;
        });
    }
}
