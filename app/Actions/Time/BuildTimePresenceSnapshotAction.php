<?php

namespace App\Actions\Time;

use App\Data\Time\TimePresenceSnapshot;
use App\Enums\WorkShiftStatus;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Models\WorkShift;

class BuildTimePresenceSnapshotAction
{
    public function handle(
        int $tenantId,
        ?int $teamId = null,
        ?int $clockPointId = null,
        ?string $search = null,
    ): TimePresenceSnapshot {
        $openShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->with(['worker.team', 'openBreak', 'clockInClockPoint'])
            ->when($teamId, fn ($q) => $q->where('internal_team_id', $teamId))
            ->when($clockPointId, fn ($q) => $q->where('clock_in_clock_point_id', $clockPointId))
            ->when($search, function ($q) use ($search) {
                $needle = mb_strtolower(trim($search));
                if ($needle === '') {
                    return;
                }
                $q->whereHas('worker', function ($workerQuery) use ($needle) {
                    $workerQuery->whereRaw('LOWER(first_name) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$needle.'%']);
                });
            })
            ->orderBy('clock_in_at')
            ->get();

        $onBreak = $openShifts->filter(fn (WorkShift $shift) => $shift->openBreak !== null)->values();
        $present = $openShifts->filter(fn (WorkShift $shift) => $shift->openBreak === null)->values();

        $clockedInWorkerIds = $openShifts->pluck('worker_id')->all();

        $notClockedIn = Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($teamId, fn ($q) => $q->where('internal_team_id', $teamId))
            ->when($clockedInWorkerIds !== [], fn ($q) => $q->whereNotIn('id', $clockedInWorkerIds))
            ->when($search, function ($q) use ($search) {
                $needle = mb_strtolower(trim($search));
                if ($needle === '') {
                    return;
                }
                $q->where(function ($inner) use ($needle) {
                    $inner->whereRaw('LOWER(first_name) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$needle.'%']);
                });
            })
            ->with('team')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($clockPointId) {
            $notClockedIn = $notClockedIn->values();
        }

        return new TimePresenceSnapshot($present, $onBreak, $notClockedIn);
    }
}
