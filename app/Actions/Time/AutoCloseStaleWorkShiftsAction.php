<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\PresenceSourceEvent;
use App\Enums\WorkShiftStatus;
use App\Events\Time\TimeShiftEnded;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

class AutoCloseStaleWorkShiftsAction
{
    public function __construct(
        private EndWorkBreakAction $endWorkBreak,
        private CloseOpenWorkShiftTaskLogsAction $closeOpenTaskLogs,
        private EnqueuePresenceFromTimeEventAction $enqueuePresence,
        private ResolveWorkShiftBreakMinutesAction $resolveBreakMinutes,
        private EndWorkVisitAction $endWorkVisit,
    ) {}

    public function handle(?int $staleHours = null): int
    {
        $closed = 0;
        $globalHours = max(1, min(24, (int) config('time.stale_shift_hours', 16)));

        Tenant::query()->each(function (Tenant $tenant) use ($staleHours, $globalHours, &$closed): void {
            Tenancy::actAs($tenant->id);

            // Checkmate: vaste serverdrempel. Facility Time: per team (default 16).
            $useTeamThreshold = ! $tenant->checkmateMode() && $staleHours === null;

            WorkShift::query()
                ->open()
                ->with('team')
                ->orderBy('id')
                ->each(function (WorkShift $shift) use ($staleHours, $globalHours, $useTeamThreshold, &$closed): void {
                    if ($shift->clock_in_at === null) {
                        return;
                    }

                    $hours = $staleHours !== null
                        ? max(1, min(24, $staleHours))
                        : ($useTeamThreshold && $shift->team !== null
                            ? $shift->team->staleShiftHours()
                            : $globalHours);

                    if ($shift->clock_in_at->greaterThanOrEqualTo(now()->subHours($hours))) {
                        return;
                    }

                    if ($this->closeShift($shift)) {
                        $closed++;
                    }
                });
        });

        Tenancy::forget();

        return $closed;
    }

    private function closeShift(WorkShift $shift): bool
    {
        return DB::transaction(function () use ($shift) {
            $locked = WorkShift::query()
                ->whereKey($shift->id)
                ->where('status', WorkShiftStatus::Open)
                ->lockForUpdate()
                ->with(['openBreak', 'breaks', 'worker', 'team'])
                ->first();

            if ($locked === null) {
                return false;
            }

            if ($locked->openBreak !== null) {
                $this->endWorkBreak->handle($locked->worker, $locked);
                $locked = $locked->fresh(['openBreak', 'breaks', 'team']);
            }

            $this->endWorkVisit->handle($locked->worker, required: false, source: ClockSource::Auto);

            $endedAt = now();
            $clockedBreakMinutes = (int) $locked->breaks->sum(fn ($break) => $break->durationMinutes());
            $totalBreakMinutes = $this->resolveBreakMinutes->handle($locked, $clockedBreakMinutes, $endedAt);

            $locked->update([
                'status' => WorkShiftStatus::ForceClosed,
                'clock_out_at' => $endedAt,
                'clock_out_source' => ClockSource::Auto,
                'clock_out_clock_point_id' => $locked->clock_out_clock_point_id ?? $locked->clock_in_clock_point_id,
                'total_break_minutes' => $totalBreakMinutes,
            ]);

            $locked = $locked->fresh(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint']);
            $this->closeOpenTaskLogs->handle($locked, $locked->clock_out_at);

            event(new TimeShiftEnded($locked, null));
            $this->enqueuePresence->handle(PresenceSourceEvent::ClockOut, $locked);

            return true;
        });
    }
}
