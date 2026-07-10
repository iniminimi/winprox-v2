<?php

namespace App\Actions\Time;

use App\Models\WorkShift;
use App\Models\WorkShiftTaskLog;
use Carbon\Carbon;

class CloseOpenWorkShiftTaskLogsAction
{
    public function handle(WorkShift $shift, ?Carbon $endedAt = null): int
    {
        $endedAt ??= $shift->clock_out_at ?? now();

        return WorkShiftTaskLog::query()
            ->where('work_shift_id', $shift->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => $endedAt]);
    }
}
