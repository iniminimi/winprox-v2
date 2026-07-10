<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
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
    ) {}

    public function handle(?int $staleHours = null): int
    {
        $hours = $staleHours ?? (int) config('time.stale_shift_hours', 16);
        $cutoff = now()->subHours(max(1, $hours));
        $closed = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($cutoff, &$closed): void {
            Tenancy::actAs($tenant->id);

            WorkShift::query()
                ->open()
                ->where('clock_in_at', '<', $cutoff)
                ->orderBy('id')
                ->each(function (WorkShift $shift) use (&$closed): void {
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
                ->with(['openBreak', 'breaks', 'worker'])
                ->first();

            if ($locked === null) {
                return false;
            }

            if ($locked->openBreak !== null) {
                $this->endWorkBreak->handle($locked->worker, $locked);
                $locked = $locked->fresh(['openBreak', 'breaks']);
            }

            $totalBreakMinutes = (int) $locked->breaks->sum(fn ($break) => $break->durationMinutes());

            $locked->update([
                'status' => WorkShiftStatus::ForceClosed,
                'clock_out_at' => now(),
                'clock_out_source' => ClockSource::Auto,
                'clock_out_clock_point_id' => $locked->clock_out_clock_point_id ?? $locked->clock_in_clock_point_id,
                'total_break_minutes' => $totalBreakMinutes,
            ]);

            event(new TimeShiftEnded($locked->fresh(), null));

            return true;
        });
    }
}
