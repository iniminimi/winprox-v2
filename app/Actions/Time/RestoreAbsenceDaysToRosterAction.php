<?php

namespace App\Actions\Time;

use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;

class RestoreAbsenceDaysToRosterAction
{
    public function handle(Tenant $tenant, AbsenceRequest $request): void
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $from = $request->date_from->toDateString();
        $to = $request->date_to->toDateString();
        $snapshot = is_array($request->replaced_shifts) ? $request->replaced_shifts : [];

        DB::transaction(function () use ($tenant, $request, $from, $to, $snapshot): void {
            PlannedShift::query()
                ->where('tenant_id', $tenant->id)
                ->where('worker_id', $request->worker_id)
                ->whereDate('work_date', '>=', $from)
                ->whereDate('work_date', '<=', $to)
                ->delete();

            foreach ($snapshot as $row) {
                if (! is_array($row) || ! isset($row['date'])) {
                    continue;
                }

                $kind = ShiftTypeKind::tryFrom((string) ($row['kind'] ?? '')) ?? ShiftTypeKind::Work;
                $status = PlannedShiftStatus::tryFrom((string) ($row['status'] ?? ''))
                    ?? PlannedShiftStatus::Published;

                PlannedShift::query()->create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $request->worker_id,
                    'work_date' => $row['date'],
                    'shift_type_id' => $row['shift_type_id'] ?? null,
                    'kind' => $kind,
                    'unit_id' => $row['unit_id'] ?? null,
                    'unit_code' => $row['unit_code'] ?? null,
                    'unit_name' => $row['unit_name'] ?? null,
                    'location_id' => $row['location_id'] ?? null,
                    'start_time' => $row['start'] ?? null,
                    'end_time' => $row['end'] ?? null,
                    'break_minutes' => (int) ($row['break_minutes'] ?? 0),
                    'description' => $row['description'] ?? null,
                    'status' => $status,
                ]);
            }
        });
    }
}
