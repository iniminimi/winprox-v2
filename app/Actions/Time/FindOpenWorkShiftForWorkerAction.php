<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Enums\WorkShiftStatus;

class FindOpenWorkShiftForWorkerAction
{
    public function handle(Worker $worker): ?WorkShift
    {
        return WorkShift::query()
            ->where('worker_id', $worker->id)
            ->where('status', WorkShiftStatus::Open)
            ->with(['openBreak', 'openVisit.unit', 'openVisit.location', 'clockInClockPoint.location', 'presenceClockPoint.location', 'clockOutClockPoint'])
            ->first();
    }
}
